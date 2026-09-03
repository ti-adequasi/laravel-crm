<?php

namespace Webkul\Sandbox\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Sandbox\Contracts\Note;

class NoteRepository extends Repository
{
    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return Note::class;
    }
}
