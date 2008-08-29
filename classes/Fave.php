<?php
/**
 * Table Definition for fave
 */
require_once 'DB/DataObject.php';

class Fave extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'fave';                            // table name
    public $notice_id;                       // int(4)  primary_key not_null
    public $user_id;                         // int(4)  primary_key not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Fave',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
}