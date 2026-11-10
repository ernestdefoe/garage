<?php

namespace ErnestDefoe\Garage\Search;

use ErnestDefoe\Garage\Comment;
use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

class CommentSearcher extends AbstractSearcher
{
    /** @return Builder<Comment> */
    public function getQuery(User $actor): Builder
    {
        return Comment::query()->select('garage_comments.*')->whereVisibleTo($actor);
    }
}
