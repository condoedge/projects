<?php

namespace Condoedge\Projects\Kompo\Concerns;

use Kompo\Auth\Facades\UserModel;

/**
 * The searchable user picker shared by every form in the module that points at a person.
 *
 * users.name carries no index over ~152k rows, so it is never ordered before being sliced —
 * doing that cost 1.3s per modal. Matching anywhere rather than on the prefix: the column holds
 * both "Denis St-Germain" and "Béland, Jean-Philippe", and a prefix search loses every
 * first-name lookup on the inverted half, for the same measured cost.
 */
trait SearchesUsers
{
    public function searchUsers()
    {
        $search = trim((string) request('search'));

        return UserModel::getClass()::query()
            ->when($search, fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->orderBy('name')
            ->limit(15)
            ->pluck('name', 'id');
    }

    /** Resolves an already-saved pick back to a label when the form reopens. */
    public function retrieveUser($id)
    {
        return UserModel::getClass()::query()->whereKey($id)->pluck('name', 'id');
    }
}
