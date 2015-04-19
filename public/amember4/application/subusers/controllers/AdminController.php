<?php

class Subusers_AdminController extends Am_Controller
{
    public function checkAdminPermissions(Admin $admin) {
        return $admin->isSuper();
    }
    function tabAction()
    {
        $user = $this->getDi()->userTable->load($this->_request->getInt('id'));
        
        $subusers_count = $user->data()->get('subusers_count');
        if (empty($subusers_count))
            throw new Am_Exception_InputError(___('This user is not a reseller'));
        
        $this->view->subusers_count = $subusers_count;
        
        $grid = Am_Grid_Editable_Subusers::factoryAdmin($user,
            $this->getRequest(), $this->view, $this->getDi());
        
        $this->view->title = ___('Subusers');
        $grid->runWithLayout('admin/subusers.phtml');
    }
}
