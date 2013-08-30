<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class ProductService extends BaseService {

    private function setDefaultFilterIfNeed(&$filter) {
        if (!$filter['page_size']) {
            $filter['page_size'] = 10;
        }
        if (!$filter['page_no']) {
            $filter['page_no'] = 1;
        }
        if (!$filter['sort_by']) {
            $filter['sort_by'] = 'id_product';
        }
        if (!$filter['sort_order']) {
            $filter['sort_order'] = 'desc';
        }
    }

    /**
     * Get the products,filter is specified by the $filter parameter
     * 
     * @param array $filter array
     * @return array
     * @author hujs
     */
    public function getProducts($filter) {
        $this->setDefaultFilterIfNeed($filter);
        $products = array('total_results' => 0, 'items' => array());
        if (isset($filter['item_ids'])) {
            // get by item ids
            $products = $this->getSpecifiedProducts($filter);
        } else if (isset($filter['is_specials']) && intval($filter['is_specials'])) {
            // get specials products
            $products = $this->getSpecialProducts($filter);
        } else if (isset($filter['query'])) {
            // get by query
            $products = $this->getProductsByQuery($filter);
        } else {
            // get by category
            $products = $this->getProductsByCategory($filter, $filter['cid']);
        }
        $returnResult = array();
        $returnResult['total_results'] = $products['total_results'];
        $returnResult['items'] = $products['items'];
        return $returnResult;
    }

    /**
     * get products by category,the category id is specified in the $filter array
     * @param type $filter
     * @return type
     * @author hujs
     */
    public function getProductsByCategory($filter) {
        $categoryId = $filter['cid'];
        $proudctTranslator = ServiceFactory::factory('ProductTranslator');

        if ($categoryId > 0) {
            $category = new Category($categoryId, $this->cookie->id_lang);
            $rows = $category->getProducts($this->cookie->id_lang, $filter['page_no'], $filter['page_size'], $filter['sort_by'], $filter['sort_order'], false);
            $total = $category->getProducts($this->cookie->id_lang, $filter['page_no'], $filter['page_size'], $filter['sort_by'], $filter['sort_order'], true);
        } else {
            $start = max(($filter['page_no'] - 1) * $filter['page_size'], 0);
            $rows = Product::getProducts($this->cookie->id_lang, $start, $filter['page_size'], $filter['sort_by'], $filter['sort_order'], false, true);
            $total = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product` p LEFT JOIN `' . _DB_PREFIX_ . 'category_product` cp ON (p.`id_product` = cp.`id_product`) WHERE  p.`active` = 1');
        }

        $products = array();
        $products['items'] = array();
        foreach ($rows as $row) {
            $proudctTranslator->clear();
            $proudctTranslator->setProduct($row);
            $proudctTranslator->getItemBaseInfo();
            $proudctTranslator->getItemPrices();
            $products['items'][] = $proudctTranslator->getTranslatedItem();
        }
        $products['total_results'] = $total;

        return $products;
    }

    /**
     * get product by name
     * @param type $filter
     * @return int
     * @author hujs
     */
    public function getProductsByQuery($filter) {
        if (is_null($filter['query'])) {
            return array('total_results' => 0, 'items' => array());
        }

        $orderBy = Tools::getProductsOrder('by', $filter['sort_by']);
        $orderWay = Tools::getProductsOrder('way', $filter['sort_order']);
        $query = stripslashes(urldecode(preg_replace('/((\%5C0+)|(\%00+))/i', '', urlencode($filter['query']))));
        $search = Search::find($this->cookie->id_lang, $query, $filter['page_no'], $filter['page_size'], $orderBy, $orderWay);

        $proudctTranslator = ServiceFactory::factory('ProductTranslator');

        foreach ($search['result'] as $row) {
            $proudctTranslator->clear();
            $proudctTranslator->setProduct($row);
            $proudctTranslator->getItemBaseInfo();
            $proudctTranslator->getItemPrices();
            $products['items'][] = $proudctTranslator->getTranslatedItem();
        }
        $products ['total_results'] = $search['total'];

        return $products;
    }

    /**
     * get special products
     * @global type $languages_id
     * @return type
     * @author hujs
     */
    public function getSpecialProducts($filter) {

        $pageNo = ($filter['page_no'] - 1 < 0 ? 0 : $filter['page_no'] - 1) * $filter['page_size'];

        $db = DB::getInstance();
        $resource = $db->ExecuteS("
			SELECT `id_product`
			FROM `" . _DB_PREFIX_ . "specific_price`
			LIMIT $pageNo, {$filter['page_size']}", false);
        $count = $db->getValue("SELECT count(*) count FROM `" . _DB_PREFIX_ . "specific_price`");

        $productTranslator = ServiceFactory::factory('ProductTranslator');
        while ($row = $db->nextRow($resource)) {
            $productTranslator->clear();
            $productTranslator->loadProductById($row['id_product']);
            $productTranslator->getItemBaseInfo();
            $productTranslator->getItemPrices();
            $items[] = $productTranslator->getTranslatedItem();
        }
        $returnResult['total_results'] = $count;
        $returnResult['items'] = $items;

        return $returnResult;
    }

    /**
     * 
     * @global type $registry
     * @global type $languages_id
     * @param array $productIds
     * @return type
     * @author hujs
     */
    public function getSpecifiedProducts($filter) {
        if (!is_array($filter['item_ids'])) {
            $filter['item_ids'] = explode(',', $filter['item_ids']);
        }
        $start = max(($filter['page_no'] - 1) * $filter['page_size'], 0);
        $productIds = array_slice($filter['item_ids'], $start, $filter['page_size']);
        $productTranslator = ServiceFactory::factory('ProductTranslator');

        $items = array();
        foreach ($productIds as $productId) {
            $productTranslator->clear();
            $productTranslator->loadProductById($productId);
            $productTranslator->getItemBaseInfo();
            $productTranslator->getItemPrices();
            $items[] = $productTranslator->getTranslatedItem();
        }
        $returnResult['total_results'] = count($productIds);
        $returnResult['items'] = $items;

        return $returnResult;
    }

    /**
     * Get one product info
     * @param integer $goods_id 商品id
     * @return array
     * @author hujs
     */
    public function getProduct($productId) {

        if (is_numeric($productId)) {
            $productTranslator = ServiceFactory::factory('ProductTranslator');
            $productTranslator->loadProductById($productId);
            return $productTranslator->getFullItemInfo();
        }
        return array();
    }

}

?>