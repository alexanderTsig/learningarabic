<?php



class Subusers_IndexController extends Am_Controller
{
    function indexAction()
    {
        $this->getDi()->auth->requireLogin($this->_request->getRequestUri());
        
        //$this->getModule()->checkAndUpdate($this->getDi()->user);
        
        $subusers_count = $this->getDi()->user->data()->get('subusers_count');
        if (empty($subusers_count))
            throw new Am_Exception_Security(___('Resellers-only page'));
        $this->view->adminHeadInit();
        $this->view->subusers_count = $subusers_count;
        
        $grid = Am_Grid_Editable_Subusers::factory($this->getDi()->user,
            $this->getRequest(), $this->view, $this->getDi());

        $pending = 0;
        foreach ($subusers_count as $v)
            if ($v['pending_count'])
                $pending+=$v['pending_count'];
        if ($pending)
        {
            $this->view->message = ___('You have too many subusers assigned to this account.  You may choose to remove %d users from your account', $pending);
        } else { 
            if ($this->getDi()->config->get('subusers_cannot_delete')==2) // no pending accounts, user cannot delete
                $grid->actionDelete('delete');
        }
            
        $grid->runWithLayout('member/subusers.phtml');
    }
    
}
