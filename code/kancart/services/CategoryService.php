<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

/**
 * @author hujs
 */
class CategoryService extends BaseService {

    /**
     * get sub categories of home
     * @return type
     * @author hujs
     */
    public function getAllCategories() {
        $root = Category::getRootCategory();
        $rows = Category::getCategories($this->cookie->id_lang);
        $categories = array();
        $parent = array();
        $postion = 0;
        foreach ($rows as $pid => $row) {
            foreach ($row as $cid => $item) {
                if ($item['infos']['level_depth'] > $root->level_depth) {
                    $pid == $root->id || $parent[$pid] = true;
                    $categories[$cid] = array(
                        'cid' => $cid,
                        'parent_cid' => $pid == $root->id ? -1 : $pid,
                        'name' => $item['infos']['name'],
                        'count' => 0,
                        'is_parent' => false,
                        'position' => $postion++
                    );
                }
            }
        }
        $this->getProductQuantity($categories, $root);
        $this->getProductTotal($categories, $parent);

        return array_values($categories);
    }

    /**
     * Calculation category include sub categroy product counts
     * @auth hujs
     * @staticvar array $children
     * @param type $cats
     * @return boolean
     */
    private function getProductTotal(&$cats, $pids) {
        if (!($count = sizeof($pids))) {//depth=1
            return;
        }

        $parents = array();
        $newPids = array();
        foreach ($cats as $key => &$cat) {
            if (isset($pids[$key])) {
                $cat['is_parent'] = true;
                $parents[$key] = &$cat;
                $newPids[$cat['parent_cid']] = true;
            } elseif ($cat['parent_cid'] != -1) {
                $cats[$cat['parent_cid']]['count'] += intval($cat['count']);
            }
        }
        $pcount = sizeof($newPids);

        while ($pcount > 1 && $count != $pcount) { //one parent or only children
            $count = $pcount;
            $pids = array();
            foreach ($parents as $key => &$parent) {
                if (!isset($newPids[$key])) {
                    if ($parent['parent_cid'] != -1) {
                        $parents[$parent['parent_cid']]['count'] += intval($parent['count']);
                    }
                    unset($parents[$key]);
                } else {
                    $pids[$parent['parent_cid']] = true;
                }
            }
            $pcount = sizeof($pids);
            $newPids = $pids;
        }
    }

    /**
     * get total of one category id
     * @param type $categoryId
     * @param type $subCategories
     * @return type
     * @author hujs
     */
    private function getProductQuantity(&$categories, $root) {
        foreach ($categories as $cid => &$category) {
            $root->id = $cid;
            $category['count'] = $root->getProducts($this->cookie->id_lang, 1, 10, null, null, true);
        }
    }

}

?>
