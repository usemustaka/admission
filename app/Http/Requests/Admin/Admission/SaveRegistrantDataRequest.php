<?php

namespace App\Http\Requests\Admin\Admission;

use App\Http\Requests\Portal\SaveRegistrantDataRequest as PortalSaveRegistrantDataRequest;

/**
 * The committee fills in the same data a parent does, whatever the status of the registration.
 */
class SaveRegistrantDataRequest extends PortalSaveRegistrantDataRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('registrant'));
    }
}
