<?php

use App\Controllers\AppController;
use App\Repositories\UserRepository;

class UserController extends AppController {
    public function getUsers() {
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

        if ($contentType = 'application/json'){
            userRepository = new UserRepository();
        }
    }
}
