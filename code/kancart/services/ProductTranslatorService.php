<?php

class ProductTranslatorService extends BaseService {

    private $product;
    private $item = array();
    private $isArray = true;
    private $tax;

    public function __construct() {
        parent::__construct();
        $this->tax = (!$this->priceDisplay || $this->priceDisplay == 2);
    }

    public function getTranslatedItem() {
        return $this->item;
    }

    public function getItemBaseInfo() {

        $this->item['item_id'] = $this->id_product;
        $this->item['item_title'] = $this->name;

        $this->item['item_url'] = $this->link->getProductLink($this->id_product);
        $this->item['qty'] = $this->quantity;
        $this->item['thumbnail_pic_url'] = $this->link->getImageLink($this->link_rewrite, $this->id_image, 'home' . PIC_NAME_SUFFIX);
        $this->item['is_virtual'] = false;
        $this->item['allow_add_to_cart'] = $this->hasAttributes();
        $this->item['item_type'] = 'simple';
        $this->item['item_status'] = $this->allowAddToCart() ? 'instock' : 'outofstock';

        $this->item['rating_count'] = null;
        $this->item['rating_score'] = null;

        return $this->item;
    }

    private function allowAddToCart() {
        $add_prod_display = Configuration::get('PS_ATTRIBUTE_CATEGORY_DISPLAY');

        return ($this->id_product_attribute == 0 || (isset($add_prod_display) && ($add_prod_display == 1))) &&
                $this->available_for_order &&
//       $this->minimal_quantity <= 1 &&
                $this->customizable != 2 &&
                !Configuration::get('PS_CATALOG_MODE') &&
                ($this->allow_oosp || $this->quantity > 0);
    }

    /**
     * Check if product has attributes combinaisons
     *
     * @return integer Attributes combinaisons number
     */
    public function hasAttributes() {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
            SELECT COUNT(`id_product_attribute`)
            FROM `' . _DB_PREFIX_ . 'product_attribute`
            WHERE `id_product` = ' . (int) ($this->id_product));
    }

    /**
     * get item prices not include tax
     * @global type $cookie
     * @return type
     * @author hujs
     */
    public function getItemPrices() {
        global $cookie;

        $prices = array();
        $productPriceWithoutRedution = Product::getPriceStatic($this->id_product, $this->tax, null, $cookie->decimals, NULL, false, false, 1);
        $finalPrice = Product::getPriceStatic($this->id_product, $this->tax, NULL, $cookie->decimals, NULL, false, true, 1);

        $prices['currency'] = $cookie->currency;
        $prices['base_price'] = array('price' => $finalPrice); //not include attribute price
        $prices['tier_prices'] = array();    //different qty has diferent price
        $displayPrices = array();
        $displayPrices[] = array(//the final price include attribute price
            'title' => 'Price',
            'price' => Tools::ps_round($finalPrice, $cookie->decimals),
            'style' => 'normal'
        );

        if ($productPriceWithoutRedution > $finalPrice) {
            $displayPrices[] = array(
                'title' => '',
                'price' => Tools::ps_round($productPriceWithoutRedution, $cookie->decimals),
                'style' => 'line-through'
            );
            $this->item['discount'] = round(100 - ($finalPrice * 100) / $productPriceWithoutRedution);
        }

        $prices['display_prices'] = $displayPrices;
        $this->item['prices'] = $prices;
        return $prices;
    }

    /**
     * get item option
     * @return type
     * @author hujs
     */
    public function getItemAttributes() {
        global $cookie;

        /* Attributes / Groups & colors */
        $colors = array();
        $combinations = array();
        $attributesGroups = $this->product->getAttributesGroups((int) $cookie->id_lang);  // @todo (RM) should only get groups and not all declination ?
        if (is_array($attributesGroups) && $attributesGroups) {
            $groups = array();
            $combinationImages = $this->product->getCombinationImages((int) $cookie->id_lang);
            foreach ($attributesGroups as $k => $row) {
                if (!Product::isAvailableWhenOutOfStock($this->product->out_of_stock) && Configuration::get('PS_DISP_UNAVAILABLE_ATTR') == 0 && !$row['quantity'])
                    continue;
                /* Color management */
                if (((isset($row['attribute_color']) && $row['attribute_color']) || (file_exists(_PS_COL_IMG_DIR_ . $row['id_attribute'] . '.jpg'))) && $row['id_attribute_group'] == $this->product->id_color_default) {
                    $colors[$row['id_attribute']]['value'] = $row['attribute_color'];
                    $colors[$row['id_attribute']]['name'] = $row['attribute_name'];
                    if (!isset($colors[$row['id_attribute']]['attributes_quantity']))
                        $colors[$row['id_attribute']]['attributes_quantity'] = 0;
                    $colors[$row['id_attribute']]['attributes_quantity'] += (int) ($row['quantity']);
                }

                if (!isset($groups[$row['id_attribute_group']]))
                    $groups[$row['id_attribute_group']] = array('name' => $row['public_group_name'], 'is_color_group' => $row['is_color_group'], 'default' => -1);

                $groups[$row['id_attribute_group']]['attributes'][$row['id_attribute']] = $row['attribute_name'];

                if ($row['default_on'] && $groups[$row['id_attribute_group']]['default'] == -1)
                    $groups[$row['id_attribute_group']]['default'] = (int) ($row['id_attribute']);
                if (!isset($groups[$row['id_attribute_group']]['attributes_quantity'][$row['id_attribute']]))
                    $groups[$row['id_attribute_group']]['attributes_quantity'][$row['id_attribute']] = 0;
                $groups[$row['id_attribute_group']]['attributes_quantity'][$row['id_attribute']] += (int) ($row['quantity']);

                $combinations[$row['id_product_attribute']]['attributes_values'][$row['id_attribute_group']] = $row['attribute_name'];
                $combinations[$row['id_product_attribute']]['attributes'][] = (int) ($row['id_attribute']);
                $combinations[$row['id_product_attribute']]['price'] = (float) ($row['price']);
                $combinations[$row['id_product_attribute']]['ecotax'] = (float) ($row['ecotax']);
                $combinations[$row['id_product_attribute']]['weight'] = (float) ($row['weight']);
                $combinations[$row['id_product_attribute']]['quantity'] = (int) ($row['quantity']);
                $combinations[$row['id_product_attribute']]['reference'] = $row['reference'];
                $combinations[$row['id_product_attribute']]['ean13'] = $row['ean13'];
                $combinations[$row['id_product_attribute']]['unit_impact'] = $row['unit_price_impact'];
                $combinations[$row['id_product_attribute']]['minimal_quantity'] = $row['minimal_quantity'];
                $combinations[$row['id_product_attribute']]['id_image'] = isset($combinationImages[$row['id_product_attribute']][0]['id_image']) ? $combinationImages[$row['id_product_attribute']][0]['id_image'] : -1;
            }

            /* Clean the attributes list (if some attributes are unavailable and if allowed to remove them) */
            if (!Product::isAvailableWhenOutOfStock($this->product->out_of_stock) && Configuration::get('PS_DISP_UNAVAILABLE_ATTR') == 0) {
                foreach ($groups as &$group)
                    foreach ($group['attributes_quantity'] as $key => &$quantity)
                        if (!$quantity)
                            unset($group['attributes'][$key]);

                foreach ($colors as $key => $color)
                    if (!$color['attributes_quantity'])
                        unset($colors[$key]);
            }

            foreach ($groups as &$group)
                natcasesort($group['attributes']);

            foreach ($combinations as $id_product_attribute => $comb) {
                $combinations[$id_product_attribute]['list'] = join('-', $comb['attributes']);
            }
        }

        $this->item['attributes'] = $this->extractAttributes($groups, $combinations);
        return $this->item['attributes'];
    }

    /**
     * get product detail information
     */
    private function extractAttributes($groups, $combinations) {
        $attributes = array();

        foreach ($groups as $key => $attr) {
            $options = array();
            foreach ($attr['attributes'] as $id => $name) {
                $isDefault = $id == $attr['default'];
                $options[] = array(
                    'attribute_id' => $key,
                    'option_id' => $id,
                    'title' => $name,
                    'price' => $isDefault ? 0 : $this->getProductAttributePrice($combinations, $attr['default'], $id),
                    'is_default' => $isDefault
                );
            }

            $attributes[] = array(
                'attribute_id' => $key,
                'title' => $attr['name'],
                'input' => 'select',
                'options' => $options
            );
        }
        return $attributes;
    }

    /**
     * only apply for one attribute
     * @global type $cookie
     * @param type $idProductAttribute
     * @return type
     */
    public function getProductAttributePrice($combinations, $default, $id) {
        if ($id && $combinations) {
            $defaultList = $combinations[$this->id_product_attribute]['list'];
            $price = $combinations[$this->id_product_attribute]['price'];
            $list = str_replace($default, $id, $defaultList);
            if ($list != $defaultList) {
                foreach ($combinations as $combination) {
                    if ($list == $combination['list']) {
                        $price = ($combination['price'] - $price) * (100 - intval($this->item['discount'])) * 0.01;
                        return currency_price_value($price);
                    }
                }
            }
        }

        return 0;
    }

    /**
     * get customer selected product information
     * @param array $options
     * @return boolean
     * @author hujs
     */
    public function getIdProductAttribut(array $options) {

        if (empty($options)) {
            return 0;
        }

        $whereStr = join(',', $options);
        $id_product_attribute = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
				SELECT id_product_attribute
                                FROM `' . _DB_PREFIX_ . 'product_attribute_combination`
				WHERE id_attribute in(' . $whereStr . ')
                                GROUP BY id_product_attribute
                                HAVING COUNT(*)=' . count($options));

        return $id_product_attribute;
    }

    public function getRecommededItems() {
        $this->item['recommended_items'] = array();
    }

    public function getRelatedItems() {
        $this->item['related_items'] = array();
    }

    public function getProductFeature() {
        global $cookie;

        $features = $this->product->getFrontFeatures($cookie->id_lang);
        foreach ($features as &$feature) {
            unset($feature['id_feature']);
        }

        return $features;
    }

    /**
     * get item images
     * @return int
     * @author hujs
     */
    public function getItemImgs() {
        global $cookie;
        $this->item['short_description'] = $this->description_short;
        $this->item['detail_description'] = preg_replace('/(\<img[^\<^\>]+src\s*=\s*[\"\'])([^(http)]+\/)/i', '$1' . _PS_BASE_URL_ . '/$2', $this->description);
        $this->item['specifications'] = $this->getProductFeature();

        $imgs = array();
        $images = $this->product->getImages($cookie->id_lang);

        $pos = 0;
        foreach ($images as $row) {
            $imageId = $this->id_product . '-' . $row['id_image'];
            $imgs[] = array(
                'id' => $row['id_image'],
                'img_url' => $this->link->getImageLink($this->link_rewrite, $imageId, 'large' . PIC_NAME_SUFFIX),
                'position' => $pos++
            );
        }

        if (!$imgs) {
            $imgs[] = array(
                'id' => '1',
                'img_url' => $this->link->getImageLink($this->link_rewrite, $this->id_image, 'large' . PIC_NAME_SUFFIX),
                'position' => $pos
            );
        }
        $this->item['item_imgs'] = $imgs;
        return $imgs;
    }

    public function clear() {
        $this->product = null;
        $this->item = array();
    }

    public function setProduct($product) {
        if (is_array($product)) {
            if (!isset($product['id_image'])) { //cid = -1
                $productId = $product['id_product'];
                $imageId = Product::getCover($productId);
                $product['id_image'] = $productId . '-' . $imageId['id_image'];
            }

            empty($product['id_product_attribute']) && $product['id_product_attribute'] = Product::getDefaultAttribute($productId);
            if (!isset($product['quantity']) || empty($product['quantity'])) {
                $product['quantity'] = StockAvailable::getQuantityAvailableByProduct($product['id_product'], $product['id_product_attribute'], Context::getContext()->shop->id);
            }

            $this->product = $product;
            $this->isArray = true;
        }
    }

    public function loadProductById($productId) {
        global $cookie;

        if (is_numeric($productId)) {
            $this->product = new Product($productId, false, $cookie->id_lang);
            $imageId = Product::getCover($productId);
            $this->product->id_image = $productId . '-' . $imageId['id_image'];
            $this->product->id_product = $productId;
            $this->product->id_product_attribute = Product::getDefaultAttribute($productId);
            if (empty($this->quantity)) {
                $this->quantity = StockAvailable::getQuantityAvailableByProduct($productId, $this->product->id_product_attribute, Context::getContext()->shop->id);
            }
            $this->product->allow_oosp = Product::isAvailableWhenOutOfStock($this->product->out_of_stock);
            $this->isArray = false;
        }
    }

    public function __get($name) {
        if ($this->isArray) {
            return $this->product[$name];
        } else {
            return $this->product->{$name};
        }
    }

    public function getFullItemInfo() {
        if ($this->isArray) {
            $this->loadProductById($this->id_product);
        }

        $this->getItemBaseInfo();
        $this->getItemPrices();
        $this->getItemAttributes();
        $this->getItemImgs();
        $this->getRecommededItems();
        $this->getRelatedItems();
        return $this->getTranslatedItem();
    }

}

?>
