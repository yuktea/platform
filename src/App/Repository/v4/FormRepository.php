<?php

/**
 * Ushahidi Form Repository
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\App\Repository\v4;

use Ohanzee\DB;
use Ushahidi\Core\Entity;
use Ushahidi\Core\Entity\v4\Form;
use Ushahidi\Core\Entity\v4\FormStage;
use Ushahidi\Core\Entity\FormRepository as FormRepositoryContract;
use Ushahidi\Core\SearchData;
use Ushahidi\Core\Tool\Permissions\InteractsWithFormPermissions;
use Ushahidi\Core\Traits\Event;
use Ushahidi\App\Repository\OhanzeeRepository;
use Ushahidi\App\Repository\FormsTagsTrait;
use League\Event\ListenerInterface;
use Illuminate\Support\Collection;
use Ushahidi\Core\Traits\UserContext;
use Ushahidi\Core\Usecase\SearchRepository;

class FormRepository extends OhanzeeRepository implements
    FormRepositoryContract,
    SearchRepository
{
    use FormsTagsTrait;
    use InteractsWithFormPermissions;

    // The access checks are run under the context of a specific user
    use UserContext;
    // Use Event trait to trigger events
    use Event;

    // OhanzeeRepository
    protected function getTable()
    {
        return 'forms';
    }

    // CreateRepository
    // ReadRepository
    public function getEntity(array $data = null)
    {
        if (isset($data["id"])) {
            $can_create = $this->getRolesThatCanCreatePosts($data['id']);
            $data = $data + [
                'can_create' => $can_create['roles'],
                'tags' => $this->getTagsForForm($data['id'])
            ];
        }

        return new Form($data);
    }

    // SearchRepository
    public function getSearchFields()
    {
        return ['parent', 'q' /* LIKE name */];
    }

    // OhanzeeRepository
    protected function setSearchConditions(SearchData $search)
    {
        $query = $this->search_query;
        if ($search->parent) {
            $query->where('parent_id', '=', $search->parent);
        }

        if ($search->q) {
            // Form text searching
            $query->where('name', 'LIKE', "%{$search->q}%");
        }
    }


    // SearchRepository
    public function getSearchResults()
    {
        $query = $this->getSearchQuery();

        $results = $query->distinct(true)->execute($this->db());
        $results = $this->hydrate($results->as_array());
        return $this->getCollection($results);
    }

    protected function getArrayOfStages($results)
    {
        return array_map(function ($item) {
            return new Entity\v4\FormStage((array) $item);
        }, $results);
    }
    protected function getArrayOfAttributes($results)
    {
        return array_map(function ($item) {
            return new Entity\v4\FormAttribute((array) $item);
        }, $results);
    }


    public function hydrate(array $forms): array {
        $results = [];
        $form_ids = array_column($forms, "id");
        $user = $this->getUser();

        $stages_query = DB::select(
            'form_stages.*',
        )
            ->from('forms')
            ->join('form_stages')
            ->on('forms.id', '=', 'form_stages.form_id')
            ->where('forms.id', 'IN', $form_ids);
        // canUserEditForm is weird and doesn't check for form_id at all :shrugs:
        if (!$this->formPermissions->canUserEditForm($user, null)) {
            $stages_query->where('show_when_published', '=', "1");
            $stages_query->where('task_is_internal_only', '=', "0");
        }
        $stages = $stages_query
            ->order_by('form_stages.id')
            ->order_by('form_stages.priority')
            ->execute($this->db())->as_array();
        $stages = $this->getArrayOfStages($stages);

        $attributes = DB::select(
            'form_attributes.*',
        )
            ->from('form_attributes')
            ->join('form_stages')
            ->on('form_attributes.form_stage_id', '=', 'form_stages.id')
            ->where('form_stages.form_id', 'IN', $form_ids)
            ->order_by('form_attributes.id')
            ->order_by('form_attributes.priority')
            ->execute($this->db())->as_array();
        $attributes = $this->getArrayOfAttributes($attributes);

        // get all attributes associated per stage, and sort them
        foreach ($stages as &$stage) {
            $stage_attrs = array_values(array_filter($attributes, function ($att) use ($stage) {
                return $att->form_stage_id == $stage->id;
            }));
            usort($stage_attrs, function ($a, $b) {
                return $a->priority >= $b->priority;
            });
            $stage->setState(['attributes' => $stage_attrs]);
        }
        // get all stages associated per form, and sort them
        foreach ($forms as $form) {
            $result = $form;
            $result['stages'] = array_values(array_filter($stages, function ($stg) use ($form) {
                return $stg->form_id == $form['id'];
            }));
            usort($result['stages'], function ($a, $b) {
                return $a->priority >= $b->priority;
            });
            $results[] = $result;
        }
        return $results;
    }

    // CreateRepository
    public function create(Entity $entity)
    {
        $id = parent::create($entity->setState(['created' => time()]));
        // todo ensure default group is created
        return $id;
    }

    // UpdateRepository
    public function update(Entity $entity)
    {
        // If orignal Form update Intercom if Name changed
        if ($entity->id === 1) {
            foreach ($entity->getChanged() as $key => $val) {
                $key === 'name' ? $this->emit($this->event, ['primary_survey_name' => $val]) : null;
            }
        }
        $form = $entity->getChanged();
        $form['updated'] = time();
        // removing tags from form before saving
        unset($form['tags']);
        // Finally save the form
        $id = $this->executeUpdate(['id'=>$entity->id], $form);

        return $id;
    }

    /**
     * Get total count of entities
     * @param  Array $where
     * @return int
     */
    public function getTotalCount(array $where = [])
    {
        return $this->selectCount($where);
    }

    /**
      * Get value of Form property type
      * if no form is found return false
      * @param  $form_id
      * @param $type, form property to check
      * @return Boolean
      */
    public function isTypeHidden($form_id, $type)
    {
        $query = DB::select($type)
            ->from('forms')
            ->where('id', '=', $form_id);

        $results = $query->execute($this->db())->as_array();

        return count($results) > 0 ? $results[0][$type] : false;
    }

    /**
     * Get `everyone_can_create` and list of roles that have access to post to the form
     * @param  $form_id
     * @return Array
     */
    public function getRolesThatCanCreatePosts($form_id)
    {
        $query = DB::select('forms.everyone_can_create', 'roles.name')
            ->distinct(true)
            ->from('forms')
            ->join('form_roles', 'LEFT')
            ->on('forms.id', '=', 'form_roles.form_id')
            ->join('roles', 'LEFT')
            ->on('roles.id', '=', 'form_roles.role_id')
            ->where('forms.id', '=', $form_id);

        $results =  $query->execute($this->db())->as_array();

        $everyone_can_create = (count($results) == 0 ? 1 : $results[0]['everyone_can_create']);

        $roles = [];

        foreach ($results as $role) {
            if (!is_null($role['name'])) {
                $roles[] = $role['name'];
            }
        }

        return [
            'everyone_can_create' => $everyone_can_create,
            'roles' => $roles,
            ];
    }


    public function hydrateAttributes(id $form_id): Collection {
        $query = DB::select(
            'form_attributes.*'
        )
            ->from('form_stages')
            ->join('form_attributes')
            ->on('form_stages.id', '=', 'form_attributes.form_stage_id')
            ->where('form_stages.form_id', '=', $form_id)
            ->order_by('form_attributes.priority');
        $results = $query->execute($this->db())->as_array();
        return new Collection($results);
    }

    public function hydrateStages(int $form_id): Collection {
        $query = DB::select(
            '*'
        )
            ->from('form_stages')
            ->where('form_stages.form_id', '=', $form_id)
            ->order_by('form_stages.id')
            ->order_by('form_stages.priority');
        $results = $query->execute($this->db())->as_array();
        return new Collection($results);
    }

    /**
     * {@inheritdoc}
     */
    public function getAllFormStagesAttributes(array $form_ids = []): Collection
    {
        $query = DB::select(
            ['forms.id', 'form_id'],
            ['form_stages.id', 'form_stage_id'],
            'form_attributes.*'
        )
            ->from('forms')
            ->join('form_stages')
            ->on('forms.id', '=', 'form_stages.form_id')
            ->join('form_attributes')
            ->on('form_stages.id', '=', 'form_attributes.form_stage_id')
            ->order_by('form_stages.id')
            ->order_by('form_stages.priority')
            ->order_by('form_attributes.priority');

        if (!empty($form_ids)) {
            $query->where('forms.id', 'IN', $form_ids);
        }

        $results = $query->execute($this->db())->as_array();

        return new Collection($results);
    }
}
