<?php

namespace Mooc\UI\ForumBlock;

use Mooc\UI\Block;
use Mooc\UI\Section\Section;

/**
 * Display the contents of an area of the core forum.
 *
 * @author <tgloeggl@uos.de>
 */
class ForumBlock extends Block
{
    const NAME = 'Forum';

    public function initialize()
    {
        $this->defineField('area_id', \Mooc\SCOPE_BLOCK, -1);

        // check, if we lost our connected area
        if ($this->area_id != -1 && !\ForumEntry::getConstraints($this->area_id)) {
            $this->area_id = -1;
        }
    }

    public function student_view()
    {
        // on view: grade with 100%
        $this->setGrade(1.0);

        // get 5 most recent topics
        $topics = \ForumEntry::getList('list', $this->area_id);
        $topics = array_values(array_slice($topics['list'], 0, 5));

        $area = \ForumEntry::getConstraints($this->area_id);

        foreach ($topics as $key => $topic) {
            $topics[$key]['human_date']   = date('d.m.Y', $topic['chdate']);
            $topics[$key]['link_to_post'] = \URLHelper::getLink('plugins.php/coreforum/index/index/' . $topic['topic_id']);
        }

        return array(
            'topics'       => $topics,
            'connected'    => !($this->area_id == -1),
            'area_name'    => $area['name'],
            'area_url' => \URLHelper::getLink('plugins.php/coreforum/index/index/' . $this->area_id)
        );
    }

    public function author_view()
    {
        $areas = \ForumEntry::getList('area', $this->container['cid']);

        if ($this->area_id != -1) {
            $area =  \ForumEntry::getConstraints($this->area_id);
        }

        return array(
            'area_id'   => $this->area_id,
            'connected' => !($this->area_id == -1),
            'area_url'  => \URLHelper::getLink('plugins.php/coreforum/index/index/' . $this->area_id),
            'area_name' => $area['name'],
            'areas'     => array_values($areas['list'])
        );
    }

    private function connectToArea($area_id)
    {
        if ($area_id == -1 || !\ForumEntry::getConstraints($area_id)) {
            // create new area and create default category if not present
            $seminar_id = $this->container['cid'];
            $user_id    = $this->container['current_user']->id;

            // predefined category must have the id md5('mooc' . seminar_id)
            $category_id = md5('mooc' . $seminar_id);

            // check preqrequisites for forum
            \ForumEntry::checkRootEntry($seminar_id);

            // check, that correct categories and areas present
            if (!\ForumCat::get($category_id)) {
                $stmt = \DBManager::get()->prepare("INSERT INTO forum_categories
                    (category_id, seminar_id, entry_name)
                    VALUES (?, ?, ?)");

                $stmt->execute(array($category_id, $seminar_id, 'Diskussionen zu den Aufgaben'));
            }

            // create new area
            $this->area_id = md5(uniqid());

            \ForumEntry::insert(array(
                'topic_id'    => $this->area_id,
                'seminar_id'  => $seminar_id,
                'user_id'     => $user_id,
                'name'        => $this->getParentTitles(),
                'author'      => get_fullname(),
                'author_host' => getenv('REMOTE_ADDR')
            ), $seminar_id);

            \ForumCat::addArea($category_id, $this->area_id);
        } else {
            $this->area_id = $area_id;
        }
    }

     // get parents' titles
    private function getParentTitles()
    {
        $path = array();
        $parent = $this->getModel();
        while ($parent = $parent->parent) {
            if ($parent->type == 'Courseware') {
                break;
            }
            $path[] = $parent->title;
        }
        return implode(' > ', array_reverse($path));
    }

    public function save_handler(array $data)
    {
        if (!$this->getCurrentUser()->canUpdate($this->getModel())) {
            throw new Errors\AccessDenied();
        }

        // store submitted connection
        $this->connectToArea($data['area_id']);

        return array();
    }
}
