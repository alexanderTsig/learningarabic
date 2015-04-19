<?php
/**
 * Class represents records from table user_group
 * {autogenerated}
 * @property int $user_group_id 
 * @property string $title 
 * @property string $description 
 * @property int $parent_id 
 * @property int $sort_order 
 * @see Am_Table
 */
class UserGroup extends Am_Record 
{
    protected $_childNodes = array();
    function getChildNodes()
    {
        return $this->_childNodes;
    }
    function createChildNode()
    {
        $c = new self;
        $c->parent_id = $this->pk();
        if (!$c->parent_id)
            throw new Am_Exception_InternalError("Could not add child node to not-saved object in ".__METHOD__);
        $this->_childNodes[] = $c;
        return $c;
    }
    public function fromRow(array $vars)
    {
        if (isset($vars['childNodes']))
        {
            foreach ($vars['childNodes'] as $row)
            {
                $r = new self($this->getTable());
                $r->fromRow($row);
                $this->_childNodes[] = $r;
            }
            unset($vars['childNodes']);
        }
        return parent::fromRow($vars);
    }
}

class UserGroupTable extends Am_Table 
{
    protected $_key = 'user_group_id';
    protected $_table = '?_user_group';
    protected $_recordClass = 'UserGroup';
   /**
     * @return ProductCategory
     */
    function getTree()
    {
        $ret = array();
        foreach ($this->_db->select("SELECT
            user_group_id AS ARRAY_KEY,
            parent_id AS PARENT_KEY, pc.*
            FROM ?_user_group AS pc
            ORDER BY 0+sort_order") as $r)
        {
            $ret[] = $this->createRecord($r);
        }
        return $ret;
    }
    function getSelectOptions(array $options = array())
    {
        $ret = array();
        $sql = "SELECT user_group_id AS ARRAY_KEY,
                parent_id, title
                FROM ?_user_group
                ORDER BY parent_id, 0+sort_order";
        $rows = $this->_db->select($sql);
        foreach ($rows as $id => $r){
            $parent_id_used = array( $id );
            $title    = $r['title'];
            $parent_id = $r['parent_id'];
            while ($parent_id)
            {
                // protect against endless cycle
                if (in_array($parent_id, $parent_id_used)) break;
                if (empty($rows[$parent_id])) break;
                $parent = $rows[$parent_id];
                $title = $parent['title'] . '/' . $title;
                $parent_id = $parent['parent_id'];
            }
            $ret [ $id ] = $title;
        }
        return $ret;
    }
    
    function moveNodes($fromId, $toId)
    {
        $this->_db->query("UPDATE {$this->_table} SET parent_id=?d WHERE parent_id=?d",
            $toId, $fromId);
    }
    public function delete($key)
    {
        parent::delete($key);
        $this->_db->query("DELETE FROM ?_user_user_group WHERE user_group_id=?d", $key);
    }
}