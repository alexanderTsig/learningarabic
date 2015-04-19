<?php
/**
 * Class represents records from table email_sent
 * {autogenerated}
 * @property int $email_sent_id 
 * @property string $subject 
 * @property string $format 
 * @property string $body 
 * @property string $files 
 * @property string $newsletter_ids 
 * @property int $count_users 
 * @property int $sent_users 
 * @property string $last_email 
 * @property string $desc_users 
 * @property string $is_cancelled 
 * @property string $serialized_vars 
 * @property datetime $tm_added 
 * @property datetime $tm_finished 
 * @property int $admin_id 
 * @see Am_Table
 */
class EmailSent extends Am_Record 
{
    protected $_serializeFields = array('subject', 'body', 'format', 'files',);

    public function unserialize()
    {
        $arr = unserialize($this->serialized_vars);
        foreach ($this->_serializeFields as $k)
        {
            $arr[$k] = $this->$k;
            if ($k == 'files')
                $arr[$k] = array_filter(explode(',', $arr[$k]));
        }
        return $arr;
    }

    public function serialize(array $arr)
    {
        foreach ($this->_serializeFields as $k)
        {
            $this->$k = @$arr[$k]; 
            unset($arr[$k]);
            if ($k == 'files' && is_array($this->$k))
                $this->$k = implode(",", $this->$k);
        }
        $this->serialized_vars = serialize($arr);
    }
}

class EmailSentTable extends Am_Table {
    protected $_key = 'email_sent_id';
    protected $_table = '?_email_sent';
    protected $_recordClass = 'EmailSent';
    
    public function insert(array $values, $returnInserted = false)
    {
        if (empty($values['tm_added']))
            $values['tm_added'] = $this->getDi()->sqlDateTime;
        return parent::insert($values, $returnInserted);
    }
}
