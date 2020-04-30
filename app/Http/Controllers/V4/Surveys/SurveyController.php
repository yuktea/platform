<?php

namespace Ushahidi\App\Http\Controllers\V4\Surveys;

use Ushahidi\App\Http\Controllers\RESTController;
use Illuminate\Http\Request;

/**
 * Ushahidi API Forms Controller
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application\Controllers
 * @copyright  2020 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

class SurveyController extends RESTController
{
    protected function getResource()
    {
        return 'v4.forms';
    }
}
