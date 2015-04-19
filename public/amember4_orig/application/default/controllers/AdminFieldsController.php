<?php

/*
 *
 *
 *     Author: Alex Scott
 *      Email: alex@cgi-central.net
 *        Web: http://www.cgi-central.net
 *    Details: New fields
 *    FileName $RCSfile$
 *    Release: 4.2.18 ($Revision$)
 *
 * Please direct bug reports,suggestions or feedback to the cgi-central forums.
 * http://www.cgi-central.net/forum/
 *
 * aMember PRO is a commercial software. Any distribution is strictly prohibited.
 *
 */

class Am_Form_Admin_CustomFields extends Am_Form_Admin
{

    protected $record;

    function __construct($record)
    {
        $this->record = $record;
        parent::__construct('fields');
    }

    function init()
    {

        $name = $this->addElement('text', 'name')
                ->setLabel(___('Field Name'));

        if (isset($this->record->name))
        {
            $name->setAttribute('disabled', 'disabled');
            $name->setValue($this->record->name);
        }
        else
        {
            $name->addRule('required', ___('This field is requred'));
            $name->addRule('callback', ___('Please choose another field name. This name is already used'), array($this, 'checkName'));
            $name->addRule('regex', ___('Name must be entered and it may contain lowercase letters, underscopes and digits'), '/^[a-z][a-z0-9_]+$/');
        }

        $title = $this->addElement('text', 'title', array('class'=>'translate'))
                ->setLabel(___('Field Title'));
        $title->addRule('required', ___('This field is requred'));

        $this->addElement('textarea', 'description', array('class'=>'translate'))
            ->setLabel(
                array(
                    ___('Field Description'), ___('for dispaying on signup and profile editing screen (for user)')
                )
        );

        $sql = $this->addElement('advradio', 'sql')
                ->setLabel(
                    array(
                        ___('Field Type'), ___('sql field will be added to table structure, common field will not, we recommend you to choose second option')
                    )
                )->loadOptions(
                array(
                    1 => ___('SQL (could not be used for multi-select and checkbox fields)'),
                    0 => ___('Not-SQL field (default)')
                )
            )->setValue(0);

        $sql->addRule('required', ___('This field is requred'));

        $sql_type = $this->addElement('select', 'sql_type')
                ->setLabel(array(
                    ___('SQL field type'), ___('if you are unsure, choose first type (string)')
                ))
                ->loadOptions(
                    array(
                        '' => '-- ' . ___('Please choose') . '--',
                        'VARCHAR(255)' => ___('String') . ' (VARCHAR(255))',
                        'TEXT' => ___('Text (unlimited length string/data)'),
                        'BLOB' => ___('Blob (unlimited length binary data)'),
                        'INT' => ___('Integer field (only numbers)'),
                        'DECIMAL(12,2)' => ___('Numeric field') . ' (DECIMAL(12,2))'
                    )
        );

        $sql_type->addRule(
            'callback',
            ___('This field is requred'),
            array(
                'callback' => array($this, 'checkSqlType'),
                'arguments' => array('fieldSql' => $sql)
            )
        );

        $this->addElement('advradio', 'type')
            ->setLabel(___('Display Type'))
            ->loadOptions(
                array(
                    'text' => ___('Text'),
                    'select' => ___('Select (Single Value)'),
                    'multi_select' => ___('Select (Multiple Values)'),
                    'textarea' => ___('TextArea'),
                    'radio' => ___('RadioButtons'),
                    'checkbox' => ___('CheckBoxes'),
                    'date'      =>  ___('Date')
                )
            )->setValue('text');

        $this->addElement('options_editor', 'values', array('class' => 'props'))
            ->setLabel(
                array(
                    ___('Field Values')
                )
            )->setValue(
            array(
                'options' => array(),
                'default' => array()
            )
        );

        $textarea = $this->addElement('group')
                ->setLabel(array('Size of textarea field', 'Columns &times; Rows'));
        $textarea->addElement('text', 'cols', array('size' => 6, 'class' => 'props'))
            ->setValue(20);
        $textarea->addElement('text', 'rows', array('size' => 6, 'class' => 'props'))
            ->setValue(5);

        $this->addElement('text', 'size', array('class' => 'props'))
            ->setLabel(___('Size of input field'))
            ->setValue(20);

        $this->addElement('text', 'default', array('class' => 'props'))
            ->setLabel(___("Default value for field\n(that is default value for inputs, not SQL DEFAULT)"));

        $el = $this->addMagicSelect('validate_func')
                ->setLabel(array(
                    ___('Validation'),
                ));
        $el->addOption(___('Required value'), 'required');
        $el->addOption(___('Integer Value'), 'integer');
        $el->addOption(___('Numeric Value'), 'numeric');
        $el->addOption(___('E-Mail Address'), 'email');

        $jsCode = <<<CUT
(function($){
	prev_opt = null;
    $("[name=type]").click(function(){
        taggleAdditionalFields(this);
    })

    $("[name=type]:checked").each(function(){
        taggleAdditionalFields(this);
    });

    $("[name=sql]").click(function(){
        taggleSQLType(this);
    })

    $("[name=sql]:checked").each(function(){
        taggleSQLType(this);
    });

    function taggleSQLType(radio) {
        if (radio.checked && radio.value == 1) {
            $("select[name=sql_type]").closest(".row").show();
        } else {
            $("select[name=sql_type]").closest(".row").hide();
        }
    }

    function clear_sql_types(){
        var elem = $("select[name='sql_type']");
        if ((elem.val()!="TEXT")) {
            prev_opt = elem.val();
            elem.val("TEXT");
        }
    }
    function back_sql_types(){
        var elem = $("select[name='sql_type']");
        if ((elem.val()=="TEXT") && prev_opt)
            elem.val(prev_opt);
    }


    function taggleAdditionalFields(radio) {
        $(".props").closest(".row").hide();
        if ( radio.checked ) {
            switch ($(radio).val()) {
                case 'text':
                    $("input[name=size],input[name=default]").closest(".row").show();
                    back_sql_types();
                    break;
                case 'textarea':
                    $("[input[name=cols],input[name=rows],input[name=default]").closest(".row").show();
                    clear_sql_types();
                    break;
                case 'date':
                    $("input[name=default]").closest(".row").show();
                    clear_sql_types();
                    break;
                case 'multi_select':
                    $("input[name=values],input[name=size]").closest(".row").show();
                    clear_sql_types();
                    break;
                case 'select':
                    $("input[name=values]").closest(".row").show();
                    clear_sql_types();
                    break;
                case 'checkbox':
                case 'radio':
                    $("input[name=values]").closest(".row").show();
                    clear_sql_types();
                break;
            }
        }
    }
})(jQuery)
CUT;


        $this->addScript('script')
            ->setScript($jsCode);
    }

    public function checkName($name)
    {
        $dbFields = Am_Di::getInstance()->userTable->getFields(true);
        if (in_array($name, $dbFields))
        {
            return false;
        }
        else
        {
            return is_null(Am_Di::getInstance()->userTable->customFields()->get($name));
        }
    }

    public function checkSqlType($sql_type, $fieldSql)
    {
        if (!$sql_type && $fieldSql->getValue())
        {
            return false;
        }
        else
        {
            return true;
        }
    }

}

class Am_Grid_DataSource_CustomFields extends Am_Grid_DataSource_Array
{

    public function insertRecord($record, $valuesFromForm)
    {
        $member_fields = Am_Di::getInstance()->config->get('member_fields');
        $recordForStore = $this->getRecordForStore($valuesFromForm);
        $recordForStore['name'] = $valuesFromForm['name'];
        $member_fields[] = $recordForStore;
        Am_Config::saveValue('member_fields', $member_fields);
        Am_Di::getInstance()->config->set('member_fields', $member_fields);

        if ($recordForStore['sql'])
        {
            $this->addSqlField($recordForStore['name'], $recordForStore['additional_fields']['sql_type']);
        }
    }

    public function updateRecord($record, $valuesFromForm)
    {
        $member_fields = Am_Di::getInstance()->config->get('member_fields');
        foreach ($member_fields as $k => $v)
        {
            if ($v['name'] == $record->name)
            {
                $recordForStore = $this->getRecordForStore($valuesFromForm);
                $recordForStore['name'] = $record->name;
                $member_fields[$k] = $recordForStore;
            }
        }
        Am_Config::saveValue('member_fields', $member_fields);
        Am_Di::getInstance()->config->set('member_fields', $member_fields);

        if ($record->sql != $recordForStore['sql'])
        {
            if ($recordForStore['sql'])
            {
                $this->convertFieldToSql($record->name, $recordForStore['additional_fields']['sql_type']);
            }
            else
            {
                $this->convertFieldFromSql($record->name);
            }
        }
        elseif ($recordForStore['sql'] &&
            $record->sql_type != $recordForStore['additional_fields']['sql_type'])
        {

            $this->changeSqlField($record->name, $recordForStore['additional_fields']['sql_type']);
        }
    }

    public function deleteRecord($id, $record)
    {
        $record = $this->getRecord($id);
        $member_fields = Am_Di::getInstance()->config->get('member_fields');
        foreach ($member_fields as $k => $v)
        {
            if ($v['name'] == $record->name)
            {
                unset($member_fields[$k]);
            }
        }
        Am_Config::saveValue('member_fields', $member_fields);
        Am_Di::getInstance()->config->set('member_fields', $member_fields);

        if ($record->sql)
        {
            $this->dropSqlField($record->name);
        }
    }

    public function createRecord()
    {
        $o = new stdclass;
        $o->name = null;
        return $o;
    }

    protected function getRecordForStore($values)
    {
        $value = array();

        if (($values['type'] == 'text') ||
            ($values['type'] == 'textarea') ||
            ($values['type'] == 'date'))
        {
            $default = $values['default'];
        }
        else
        {
        $default = array_intersect($values['values']['default'], array_keys($values['values']['options']));            
        if ($values['type'] == 'radio')
                $default = $default[0];
        }

        if ($values['type'] == 'select') $values['size'] = 1;

        $recordForStore['title'] = $values['title'];
        $recordForStore['description'] = $values['description'];
        $recordForStore['sql'] = $values['sql'];
        $recordForStore['type'] = $values['type'];
        $recordForStore['validate_func'] = $values['validate_func'];
        $recordForStore['additional_fields'] = array(
            'sql' => intval($values['sql']),
            'sql_type' => $values['sql_type'],
            'size' => $values['size'],
            'default' => $default,
            'options' => $values['values']['options'],
            'cols' => $values['cols'],
            'rows' => $values['rows'],
        );

        return $recordForStore;
    }

    protected function addSqlField($name, $type)
    {
        Am_Di::getInstance()->db->query("ALTER TABLE ?_user ADD ?# $type", $name);
    }

    protected function dropSqlField($name)
    {
        Am_Di::getInstance()->db->query("ALTER TABLE ?_user DROP ?#", $name);
    }

    protected function changeSqlField($name, $type)
    {
        Am_Di::getInstance()->db->query("ALTER TABLE ?_user CHANGE ?# ?# $type", $name, $name);
    }

    protected function convertFieldToSql($name, $type)
    {
        $this->addSqlField($name, $type);
        Am_Di::getInstance()->db->query("UPDATE ?_user u SET ?# = (SELECT `value`
            FROM ?_data
            WHERE `table`='user'
            AND `key`= ?
            AND `id`=u.user_id LIMIT 1)", $name, $name);
        Am_Di::getInstance()->db->query("DELETE FROM ?_data WHERE `table`='user' AND `key`=?", $name);
    }

    protected function convertFieldFromSql($name)
    {
        Am_Di::getInstance()->db->query("INSERT INTO ?_data (`table`, `key`, `id`, `value`)
            (SELECT 'user', ?, user_id, ?# FROM ?_user)", $name, $name);

        $this->dropSqlField($name);
    }

    public function getDataSourceQuery()
    {
        return null;
    }

}

class AdminFieldsController extends Am_Controller_Grid
{

    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->isSuper();
    }

    public function createGrid()
    {
        $fields = Am_Di::getInstance()->userTable->customFields()->getAll();
        uksort($fields, array(Am_Di::getInstance()->userTable,'sortCustomFields'));
        $ds = new Am_Grid_DataSource_CustomFields($fields);
        $grid = new Am_Grid_Editable('_f', ___('Additional Fields'), $ds, $this->_request, $this->view);
        $grid->addField(new Am_Grid_Field('name', ___('Name'), true, '', null, '10%'));
        $grid->addField(new Am_Grid_Field('title', ___('Title'), true, '', null, '20%'));
        $grid->addField(new Am_Grid_Field('sql', ___('Field Type'), true, '', null, '10%'))
            ->setRenderFunction(array($this, 'renderFieldType'));
        $grid->addField(new Am_Grid_Field('type', ___('Display Type'), true, '', null, '10%'));
        $grid->addField(new Am_Grid_Field('description', ___('Description'), false, '', null, '40%'));
        $grid->addField(new Am_Grid_Field('validateFunc', ___('Validation'), false, '', null, '20%'))
            ->setGetFunction(create_function('$r', 'return implode(",", (array)$r->validateFunc);'));

        $grid->setForm(array($this, 'createForm'));
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, array($this, 'valuesToForm'));
        $grid->addCallback(Am_Grid_Editable::CB_AFTER_DELETE, array($this, 'afterDelete'));
        $grid->addCallback(Am_Grid_ReadOnly::CB_TR_ATTRIBS, array($this, 'getTrAttribs'));

        $grid->actionGet('edit')->setIsAvailableCallback(create_function('$record', 'return isset($record->from_config) && $record->from_config;'));
        $grid->actionGet('delete')->setIsAvailableCallback(create_function('$record', 'return isset($record->from_config) && $record->from_config;'));
        
        $grid->actionAdd(new Am_Grid_CustomFields_Action_SortContent());
        
        $grid->setRecordTitle(___('Field'));
        return $grid;
    }

    public function renderFieldType($record, $fieldName, Am_Grid_ReadOnly $grid) {
        return $grid->renderTd(!empty($record->sql) ? '[SQL]' : '[DATA]');
    }

    public function createForm()
    {
        return new Am_Form_Admin_CustomFields($this->grid->getRecord());
    }

    public function getTrAttribs(& $ret, $record)
    {
        if (isset($record->from_config) && $record->from_config)
        {
            //
        }
        else
        {
            $ret['class'] = isset($ret['class']) ? $ret['class'] . ' disabled' : 'disabled';
        }
    }

    public function valuesToForm(& $ret, $record)
    {
        $ret['validate_func'] = @$record->validateFunc;

        $ret['values'] = array(
            'options' => (array) @$record->options,
            'default' => (array) @$record->default
        );
    }

    public function afterDelete($record)
    {
        foreach ($this->getDi()->savedFormTable->findBy() as $savedForm)
        {
            if ($row = $savedForm->findBrickById('field-' . $record->name))
            {
                $savedForm->removeBrickConfig($row['class'], $row['id']);
                $savedForm->update();
            }
        }
    }

}

class Am_Grid_CustomFields_Action_SortContent extends Am_Grid_Action_Abstract
{
    protected $privilege = 'edit';
    protected $type = self::HIDDEN;
    protected $fieldName;
    protected $callback;
    /** @var Am_Grid_Decorator_LiveEdit */
    protected $decorator;
    protected static $jsIsAlreadyAdded = false;
    
    public function setGrid(Am_Grid_Editable $grid)
    {
        $grid->addCallback(Am_Grid_ReadOnly::CB_TR_ATTRIBS, array($this, 'getTrAttribs'));
        $grid->addCallback(Am_Grid_Editable::CB_RENDER_STATIC, array($this, 'renderStatic'));
        return parent::setGrid($grid);
    }
    function getTrAttribs(array & $attribs, $obj)
    {
        $attribs['data-id'] = $id = $obj->name;
        $grid_id = $this->grid->getId();
        $params = array(
            $grid_id . '_' . Am_Grid_ReadOnly::ACTION_KEY => $this->getId(),
            $grid_id . '_' . Am_Grid_ReadOnly::ID_KEY => $id,
        );
        $attribs['data-params'] = json_encode($params);
    }
    public function renderStatic(& $out, Am_Grid_Editable $grid)
    {
        $url = json_encode($grid->makeUrl());
        $grid_id = $this->grid->getId();
        $msg = ___("Drag&Drop rows to change display order. You may want to temporary change setting '%sRecords per page (for grids)%s' to some big value so all records were on one page and you can arrange all items.",
            '<a href="' . REL_ROOT_URL . '/admin-setup">','</a>');
        $out .= <<<CUT
<i><div class="am-grid-drag-sort-message">$msg</div></i>
<script type="text/javascript">
jQuery(function($){
    $(".grid-wrap").ngrid("onLoad", function(){
        if ($(this).find("th .sorted-asc, th .sorted-desc").length)
        {
            $(this).sortable( "destroy" );
            return;
        }

        $(this).sortable({
            items: "tbody > tr.grid-row",
            update: function(event, ui) {
                var item = $(ui.item);
                var url = $url;
                var prevId = item.prev().data('id');
                var nextId = item.next().data('id');
                var params = item.data('params');
                params.{$grid_id}_move_before =  nextId ? nextId : '';
                params.{$grid_id}_move_after =  prevId ? prevId : '';
                $.post(url, params, function(response){
                });
            },
        });
    });
});
</script>
CUT;
    }
    
    public function run()
    {
        $request = $this->grid->getRequest();
        $id = $request->getFiltered('id');
        $move_before = $request->getFiltered('move_before');
        $move_after = $request->getFiltered('move_after');
        
        /*$record = $accessTables[$type]->load($id, false);
        if (!$record) 
            throw new Am_Exception_InputError("Record [$id] not found");*/

        $resp = array(
            'ok' => true,
        );
        if ($this->callback)
            $resp['callback'] = $this->callback;
        try {
            $this->setSortBetween($id, $move_after, $move_before);
        } catch (Exception $e) {
            throw $e;
            $resp = array('ok' => false, );
        }
        Am_Controller::ajaxResponse($resp);
        exit();
    }
    function setSortBetween($id, $after, $before)
    {
        $db = Am_Di::getInstance()->db;
        if ($before)
        {
            $beforeSort = (int)$db->selectCell("SELECT sort_order
                FROM ?_custom_fields_sort
                WHERE custom_field_name=? and custom_field_table = 'user'
            ", $before);
            if (!$beforeSort) return ; // something is wrong
            if (!$prevRow = $db->selectRow("SELECT custom_field_name
                FROM ?_custom_fields_sort
                WHERE sort_order=? and custom_field_table = 'user'", $beforeSort-1))
            {
                $this->setSortOrder($id, $beforeSort-1);
            } else { // $prevRow is exists 
                if ($prevRow['custom_field_name'] == $id)
                    return; // we already have it set correctly
                // prevRow is busy, lets do shift
                $db->query("UPDATE ?_resource_access_sort
                    SET sort_order=sort_order+1
                    WHERE sort_order >= ?d", $beforeSort);
                $this->setSortOrder($id, $beforeSort);
            }
        } elseif ($after) {
            $afterSort = (int)$db->selectCell("SELECT sort_order
                FROM ?_custom_fields_sort
                WHERE custom_field_name=? and custom_field_table = 'user'
            ", $after);
            if (!$afterSort) return ; // something is wrong
            if (!$prevRow = $db->selectRow("SELECT custom_field_name
                FROM ?_custom_fields_sort
                WHERE sort_order=?", $afterSort+1))
            {
                $this->setSortOrder($id, $afterSort+1);
            } else { // $prevRow is exists 
                if ($prevRow['custom_field_name'] == $id)
                    return; // we already have it set correctly
                // prevRow is busy, lets do shift
                $db->query("UPDATE ?_custom_fields_sort
                    SET sort_order=sort_order+1
                    WHERE sort_order >= ?d", $afterSort+1);
                $this->setSortOrder($id, $afterSort+1);
            }
        }
    }
    function setSortOrder($id,$sort)
    {
        Am_Di::getInstance()->db->query(
            "INSERT INTO ?_custom_fields_sort 
             SET custom_field_name=?, sort_order=?d, custom_field_table = 'user'
             ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order)",
            $id, $sort
        );
    }    
}
