<?php

/**
 * Ushahidi API Formatter for Form Stage
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\App\Formatter\v4;

use Ushahidi\App\Formatter\API;
use Ushahidi\Core\Traits\FormatterAuthorizerMetadata;

class Stage extends API
{
    use FormatterAuthorizerMetadata;

    protected function formatAttributes($attributes)
    {
        $formatter = service('formatter.entity.v4.attributes');
        $return = [];
        foreach ($attributes as $attribute) {
            $return[] = $formatter->__invoke($attribute);
        }
        return $return;
    }
}
