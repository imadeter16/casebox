<?php
namespace CB\TreeNode;

use CB\L;
use CB\Templates;

class Tasks extends Base
{
    protected function acceptedPath()
    {
        $p = &$this->path;

        // Tasks can't be a root folder
        if (sizeof($p) == 0) {
            return false;
        }

        //get the configured 'pid' property for this tree plugin
        //default is 0
        //thats the parent node id where this class shold start to give result nodes
        $ourPid = @$this->config['pid'];
        if ($ourPid == '') {
            $ourPid = '0';
        }
    \CB\debug($this->lastNode->id, (String)$ourPid);
        // ROOT NODE: check if last node is the one we should attach to
        if ($this->lastNode->id == (String)$ourPid) {
            \CB\debug('root NODE');

            return true;
        }

        // CHILDREN NODES: accept if last node is an instance of this class
        if (get_class($this->lastNode) ==  get_class($this)) {
            \CB\debug('CHILDREN NODES');

            return true;
        }

        return false;
    }

    protected function createDefaultFilter()
    {
        $this->fq = array();

        //select only task templates
        $taskTemplates = Templates::getIdsByType('task');
        if (!empty($taskTemplates)) {
            $this->fq[] = 'template_id:('.implode(' OR ', $taskTemplates).')';
        }

    }

    public function getChildren(&$pathArray, $requestParams)
    {

        $this->path = $pathArray;
        $this->lastNode = @$pathArray[sizeof($pathArray) - 1];
        $this->requestParams = $requestParams;
        $this->rootId = \CB\Browser::getRootFolderId();

        if (!$this->acceptedPath()) {
        \CB\debug('deny', get_class($this));

            return;
        }
        \CB\debug('allow', get_class($this));

        $ourPid = @($this->config['pid']);
        if ($ourPid == '') {
            $ourPid = 0;
        }

        // \CB\debug("ourPid: " . $ourPid . ", lastNode.id: " . $this->lastNode->id);

        $this->createDefaultFilter();

        if (empty($this->lastNode) ||
            (($this->lastNode->id == $ourPid) && (get_class($this->lastNode) != get_class($this))) ||
            (\CB\Objects::getType($this->lastNode->id) == 'case')
        ) {
            $rez = $this->getRootNodes();
        } else {
            switch ($this->lastNode->id) {
                case 'tasks':
                    $rez = $this->getDepthChildren2();
                    break;
                case 2:
                case 3:
                    $rez = $this->getDepthChildren3();
                    break;
                default:
                    $rez = $this->getChildrenTasks();
            }
        }

        return $rez;
    }

    public function getName($id = false)
    {
        if ($id === false) {
            $id = $this->id;
        }
        switch ($id) {
            case 'tasks':
                return L\get('Tasks');
            case 2:
                return L\get('AssignedToMe');
            case 3:
                return L\get('Created');
            case 4:
                return lcfirst(L\get('Overdue'));
            case 5:
                return lcfirst(L\get('Ongoing'));
            case 6:
                return lcfirst(L\get('Closed'));
            case 'assignee':
                return lcfirst(L\get('Assignee'));
            default:
                if (substr($id, 0, 3) == 'au_') {
                    return \CB\User::getDisplayName(substr($id, 3));
                }
        }

        return 'none';
    }

    protected function getRootNodes()
    {
        $p = $this->requestParams;
        $p['fq'] = $this->fq;
        $p['fq'][] = '(user_ids:'.$_SESSION['user']['id'].' OR cid:'.$_SESSION['user']['id'].')';
        $p['fq'][] = 'task_status:(1 OR 2)';
        $p['rows'] = 0;

        $s = new \CB\Search();
        $rez = $s->query($p);
        $count = '';
        if (!empty($rez['total'])) {
            $count = ' ('.$rez['total'].')';
        }

        return array(
            'data' => array(
                array(
                    'name' => L\get('Tasks') . $count
                    ,'id' => $this->getId('tasks')
                    ,'iconCls' => 'icon-task'
                    ,'has_childs' => true
                )
            )
        );
    }

    protected function getDepthChildren2()
    {
        $p = $this->requestParams;
        $p['fq'] = $this->fq;
        $p['fq'][] = '(user_ids:'.$_SESSION['user']['id'].' OR cid:'.$_SESSION['user']['id'].')';
        $p['fq'][] = 'task_status:(1 OR 2)';

        if (@$this->requestParams['from'] == 'tree') {
            $s = new \CB\Search();
            $p['rows'] = 0;
            $p['facet'] = true;
            $p['facet.field'] = array(
                '{!ex=user_ids key=1assigned}user_ids'
                ,'{!ex=cid key=2cid}cid'
            );
            $sr = $s->query($p);
            $rez = array('data' => array());
            if (!empty($sr['facets']->facet_fields->{'1assigned'}->{$_SESSION['user']['id']})) {
                $rez['data'][] = array(
                    'name' => L\get('AssignedToMe').' ('.$sr['facets']->facet_fields->{'1assigned'}->{$_SESSION['user']['id']}.')'
                    ,'id' => $this->getId(2)
                    ,'iconCls' => 'icon-task'
                    ,'has_childs' => true
                );
            }
            if (!empty($sr['facets']->facet_fields->{'2cid'}->{$_SESSION['user']['id']})) {
                $rez['data'][] = array(
                    'name' => L\get('Created').' ('.$sr['facets']->facet_fields->{'2cid'}->{$_SESSION['user']['id']}.')'
                    ,'id' => $this->getId(3)
                    ,'iconCls' => 'icon-task'
                    ,'has_childs' => true
                );
            }

            return $rez;
        }

        // for other views
        $s = new \CB\Search();
        $rez = $s->query($p);

        return $rez;
    }

    protected function getDepthChildren3()
    {
        $p = $this->requestParams;
        $p['fq'] = $this->fq;

        if ($this->lastNode->id == 2) {
            $p['fq'][] = 'user_ids:'.$_SESSION['user']['id'];
        } else {
            $p['fq'][] = 'cid:'.$_SESSION['user']['id'];
        }

        $rez = array();

        if (@$this->requestParams['from'] == 'tree') {
            $s = new \CB\Search();

            $sr = $s->query(
                array(
                    'rows' => 0
                    ,'fq' => $p['fq']
                    ,'facet' => true
                    ,'facet.field' => array(
                        '{!ex=task_status key=0task_status}task_status'
                    )
                )
            );
            $rez = array('data' => array());
            if (!empty($sr['facets']->facet_fields->{'0task_status'}->{'1'})) {
                $rez['data'][] = array(
                    'name' => lcfirst(L\get('Overdue')).' ('.$sr['facets']->facet_fields->{'0task_status'}->{'1'}.')'
                    ,'id' => $this->getId(4)
                    ,'iconCls' => 'icon-task'
                );
            }
            if (!empty($sr['facets']->facet_fields->{'0task_status'}->{'2'})) {
                $rez['data'][] = array(
                    'name' => lcfirst(L\get('Ongoing')).' ('.$sr['facets']->facet_fields->{'0task_status'}->{'2'}.')'
                    ,'id' => $this->getId(5)
                    ,'iconCls' => 'icon-task'
                );
            }
            if (!empty($sr['facets']->facet_fields->{'0task_status'}->{'3'})) {
                $rez['data'][] = array(
                    'name' => lcfirst(L\get('Closed')).' ('.$sr['facets']->facet_fields->{'0task_status'}->{'3'}.')'
                    ,'id' => $this->getId(6)
                    ,'iconCls' => 'icon-task'
                );
            }
            // Add assignee node if there are any created tasks already added to result
            if (($this->lastNode->id == 3) && !empty($rez['data'])) {
                $rez['data'][] = array(
                    'name' => lcfirst(L\get('Assignee'))
                    ,'id' => $this->getId('assignee')
                    ,'iconCls' => 'icon-task'
                    ,'has_childs' => true
                );
            }
        } else {

            $p['fq'][] = 'task_status:(1 OR 2)';

            $s = new \CB\Search();
            $rez = $s->query($p);
            foreach ($rez['data'] as &$n) {
                $n['has_childs'] = true;
            }
        }

        return $rez;
    }

    protected function getChildrenTasks()
    {
        $rez = array();

        $p = $this->requestParams;
        $p['fq'] = $this->fq;

        $parent = $this->lastNode->parent;

        if ($parent->id == 2) {
            $p['fq'][] = 'user_ids:'.$_SESSION['user']['id'];
        } else {
            $p['fq'][] = 'cid:'.$_SESSION['user']['id'];
        }

        // please don't use numeric IDs for named folders: "Assigned to me", "Overdue" etc
        switch ($this->lastNode->id) {
            case 4:
                $p['fq'][] = 'task_status:1';
                break;
            case 5:
                $p['fq'][] = 'task_status:2';
                break;
            case 6:
                $p['fq'][] = 'task_status:3';
                break;
            case 'assignee':
                return $this->getAssigneeUsers();
                break;
            default:
                if (substr($this->lastNode->id, 0, 3) == 'au_') {
                    return $this->getAssigneeTasks();
                }
        }

        if (@$this->requestParams['from'] == 'tree') {
            return $rez;
        }

        $s = new \CB\Search();
        $rez = $s->query($p);

        return $rez;
    }

    protected function getAssigneeUsers()
    {
        $p = $this->requestParams;
        $p['fq'] = $this->fq;

        $p['fq'][] = 'cid:'.$_SESSION['user']['id'];
        $p['fq'][] = 'task_status:[1 TO 2]';

        $p['rows'] = 0;
        $p['facet'] = true;
        $p['facet.field'] = array(
            '{!ex=user_ids key=user_ids}user_ids'
        );
        $rez = array();

        $s = new \CB\Search();

        $sr = $s->query($p);

        $rez = array('data' => array());
        if (!empty($sr['facets']->facet_fields->{'user_ids'})) {
            foreach ($sr['facets']->facet_fields->{'user_ids'} as $k => $v) {
                $k = 'au_'.$k;
                $r = array(
                    'name' => $this->getName($k).' ('.$v.')'
                    ,'id' => $this->getId($k)
                    ,'iconCls' => 'icon-user'
                );

                if (!empty($p['showFoldersContent']) ||
                    (@$this->requestParams['from'] != 'tree')
                ) {
                    $r['has_childs'] = true;
                }
                $rez['data'][] = $r;
            }
        }

        return $rez;
    }

    protected function getAssigneeTasks()
    {
        $p = $this->requestParams;
        $p['fq'] = $this->fq;

        $p['fq'][] = 'cid:'.$_SESSION['user']['id'];
        $p['fq'][] = 'task_status:[1 TO 2]';

        $user_id = substr($this->lastNode->id, 3);
        $p['fq'][] = 'user_ids:'.$user_id;

        $s = new \CB\Search();

        $sr = $s->query($p);

        return $sr;
    }
}
