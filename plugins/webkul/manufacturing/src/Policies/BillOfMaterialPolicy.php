<?php

namespace Webkul\Manufacturing\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Webkul\Manufacturing\Models\BillOfMaterial;
use Webkul\Security\Models\User;

class BillOfMaterialPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_manufacturing_bill::of::material');
    }

    public function view(User $user, BillOfMaterial $billOfMaterial): bool
    {
        return $user->can('view_manufacturing_bill::of::material');
    }

    public function create(User $user): bool
    {
        return $user->can('create_manufacturing_bill::of::material');
    }

    public function update(User $user, BillOfMaterial $billOfMaterial): bool
    {
        return $user->can('update_manufacturing_bill::of::material');
    }

    public function delete(User $user, BillOfMaterial $billOfMaterial): bool
    {
        return $user->can('delete_manufacturing_bill::of::material');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_manufacturing_bill::of::material');
    }

    public function forceDelete(User $user, BillOfMaterial $billOfMaterial): bool
    {
        return $user->can('force_delete_manufacturing_bill::of::material');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_manufacturing_bill::of::material');
    }

    public function restore(User $user, BillOfMaterial $billOfMaterial): bool
    {
        return $user->can('restore_manufacturing_bill::of::material');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_manufacturing_bill::of::material');
    }
}
