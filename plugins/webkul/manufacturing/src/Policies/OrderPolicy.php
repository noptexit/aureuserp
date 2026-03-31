<?php

namespace Webkul\Manufacturing\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Webkul\Manufacturing\Models\Order;
use Webkul\Security\Models\User;

class OrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_manufacturing_order');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can('view_manufacturing_order');
    }

    public function create(User $user): bool
    {
        return $user->can('create_manufacturing_order');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can('update_manufacturing_order');
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->can('delete_manufacturing_order');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_manufacturing_order');
    }
}
