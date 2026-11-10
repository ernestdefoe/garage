<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

// Builds are made to be shown off: everyone may look; members add, like and comment; moderators tidy up.
return Migration::addPermissions([
    'ernestdefoe-garage.view' => Group::GUEST_ID,
    'ernestdefoe-garage.create' => Group::MEMBER_ID,
    'ernestdefoe-garage.like' => Group::MEMBER_ID,
    'ernestdefoe-garage.comment' => Group::MEMBER_ID,
    'ernestdefoe-garage.moderate' => Group::MODERATOR_ID,
]);
