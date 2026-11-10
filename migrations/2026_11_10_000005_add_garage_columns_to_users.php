<?php

use Flarum\Database\Migration;

// The profile tab's count, and the build a member shows under their posts (with FoF Signature).
return Migration::addColumns('users', [
    'garage_build_count' => ['integer', 'unsigned' => true, 'default' => 0],
    'garage_signature_build_id' => ['integer', 'unsigned' => true, 'nullable' => true],
]);
