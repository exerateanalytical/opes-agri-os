<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base for every v1 API controller.
 *
 * The response envelope itself needs no helper here: JsonResource and
 * ResourceCollection already wrap their payload in `"data"` by default, which
 * is the success half of the Stripe-style envelope this API uses. This class
 * exists so every controller shares one authorization entry point
 * (`$this->authorize(...)`, checked against the same Policies the web app
 * uses) and one place to add cross-cutting behaviour later.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
}
