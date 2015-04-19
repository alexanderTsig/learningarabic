<?php

/**
 * Class represents records from table helpdesk_snippet
 * {autogenerated}
 * @property int $snippet_id
 * @property string $title
 * @property string $content 
 * @see Am_Table
 */
class HelpdeskSnippet extends Am_Record {
    protected $_key = 'snippet_id';
    protected $_table = '?_helpdesk_snippet';
}

class HelpdeskSnippetTable extends Am_Table {
    protected $_key = 'snippet_id';
    protected $_table = '?_helpdesk_snippet';
    protected $_recordClass = 'HelpdeskSnippet';
}


