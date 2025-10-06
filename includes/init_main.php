<?php
use App\Core\Database;

function getMainPDO() {
    return Database::getInstance()->getConnection();
}
