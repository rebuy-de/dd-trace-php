<?php

if (in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
    return;
}


$classes = [];
