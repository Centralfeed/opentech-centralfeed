<?php

class OpentechCentralfeedExporter
{

    /*
     * @var array
     */
    protected $categories;
    protected $products_categories;
    protected $attributes;
    protected $variations;
    protected $custom_options;
    protected $attribute_values;

    const EXPORT_TYPE_ALL = 'all';
    const EXPORT_TYPE_STOCK = 'stock';

    protected $debug_product;
    protected $blog_name;

    public function __construct(){
        gc_enable();

        //    ini_set('display_errors', '1');
        //      ini_set('error_reporting', E_ALL);

        // Increase maximum execution time to 4 hours
        ini_set('max_execution_time', 14400);
        ini_set('memory_limit', '-1');


        $this->blog_name =  get_bloginfo( 'name' );

        if (isset($_GET['debug']) && is_numeric($_GET['debug'])){
            $this->debug_product = $_GET['debug'];
        }
        else $this->debug_product = false;
    }

    public function centralfeed_export($type)
    {
     $this->centralfeed_json_header();
        $result = '';
        switch ($type){
            case self::EXPORT_TYPE_ALL:
                $result = $this->centralfeed_export_dataset();
                break;
            case self::EXPORT_TYPE_STOCK:
                $result = $this->centralfeed_export_stock();
                break;
            default:
                break;
        }
        echo json_encode($result);
        exit;
    }

    // Export process for CSV file
    protected function centralfeed_export_dataset()
    {
        $this->centralfeed_get_all_attributes();
        $this->centralfeed_get_all_categories();
        $this->centralfeed_get_all_products_categories();
        $output = '';
        $products = $this->centralfeed_get_products();
        if (count($products )) {
            $output = $this->centralfeed_get_product_data($products);
        }
        unset($products);
        return array_values($output);
    }
    // Export process for CSV file
    protected function centralfeed_export_stock()
    {
        $output = '';
        $products = $this->centralfeed_get_products();
        if (count($products )) {
            $output = $this->centralfeed_get_product_basic_data($products);
        }
        unset($products);
        return array_values($output);
    }

// Escape all cells in 'Excel' CSV escape formatting of a CSV file, also converts HTML entities to plain-text
    protected function centralfeed_escape_csv_value($string = '')
    {
        $string = str_replace('"', '""', $string);
        $string = wp_specialchars_decode($string);
        $string = str_replace(PHP_EOL, "\r\n", $string);


        if (strpos($string, '"') !== false or strpos($string, ',') !== false or strpos($string, "\r") !== false or strpos($string, "\n") !== false)
            $string = '"' . $string . '"';

        return $string;

    }

    protected function centralfeed_file_encoding($content = '')
    {
        if (function_exists('mb_convert_encoding')) {
            $content = utf8_encode($content);
        }
        return $content;
    }

    protected function centralfeed_json_header()
    {
        header(sprintf('Content-Encoding: %s', 'UTF-8'));
        header('Content-Type: application/json');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

// Returns a list of WooCommerce Products
    protected function centralfeed_get_products()
    {
        global $wpdb;
        $products = array();
        if ($this->debug_product){
            $product =(function_exists('wc_get_product') ? wc_get_product($this->debug_product) : false);
            $products = array_merge(array($this->debug_product),$product->get_children());
            return $products;
        }

        $product_ids = $wpdb->get_results("select p.id,p.post_parent,p.post_type,pm.meta_value from ".$wpdb->prefix."posts p
left join ".$wpdb->prefix."postmeta pm on p.id=pm.post_id and pm.meta_key='_downloadable'
where p.post_type in ('product', 'product_variation') and  p.post_status = 'publish'
order by ID ASC",ARRAY_A);

        if ($product_ids) {
            foreach ($product_ids as $key=>$prod_obj) {
                $product_id = $prod_obj['id'];

                // Filter out Variations that don't have a Parent Product that exists
                if ($prod_obj['post_type'] == 'product_variation') {
                    // Check if Parent exists
                    if (!in_array( $prod_obj['post_parent'],$products)) {
                        unset($product_id,$prod_obj);
                        continue;
                    }
                }

                // check that is not downloadable
                if( $prod_obj['meta_value']=='yes'){
                    unset($product_id,$prod_obj);
                    continue;
                }

                    $products[] = $product_id;
            }

            unset($product_ids, $product_id,$prod_obj);
        }
        gc_collect_cycles();

        return $products;
    }

    /**
     * @param  array $products
     * @return object
     */
    protected function centralfeed_get_product_data($products)
    {
        global $wpdb;
        $data = array();
        $basic = $this->centralfeed_get_product_basic_data($products);
        $as_is_data =
         $wpdb->get_results("select p.id,
            p.post_title as 'name',
            pm1.meta_value as price,
               p.post_parent as parent_id,
            1 as visibility
            from ".$wpdb->prefix."posts p
            left join ".$wpdb->prefix."postmeta pm1 on p.id=pm1.post_id and pm1.meta_key='_regular_price'
            where p.id in (".implode(',',$products).")
            order by ID ASC", OBJECT_K);

        $wpdb->query('SET SQL_BIG_SELECTS=1;');
        $more_data =
         $wpdb->get_results("select p.id,
            pm2.meta_value as special_price,
            pm1.meta_value as primary_image,
            pm3.meta_value as image_gallery,
            pm4.meta_value as variation_price,
            pm5.meta_value as variation_sale_price,
            pm6.meta_value as special_from_date,
            pm7.meta_value as special_to_date,
            p.post_type,
            p.post_content as description,
            p.post_excerpt as short_description

            from ".$wpdb->prefix."posts p
            left join ".$wpdb->prefix."postmeta pm2 on p.id=pm2.post_id and pm2.meta_key='_sale_price'
            left join ".$wpdb->prefix."postmeta pm1 on p.id=pm1.post_id and pm1.meta_key='_thumbnail_id'
            left join ".$wpdb->prefix."postmeta pm3 on p.id=pm3.post_id and pm3.meta_key='_product_image_gallery'
            left join ".$wpdb->prefix."postmeta pm4 on p.id=pm4.post_id and pm4.meta_key='_min_variation_regular_price'
            left join ".$wpdb->prefix."postmeta pm5 on p.id=pm5.post_id and pm5.meta_key='_min_variation_sale_price'
            left join ".$wpdb->prefix."postmeta pm6 on p.id=pm6.post_id and pm6.meta_key='_sale_price_dates_from'
            left join ".$wpdb->prefix."postmeta pm7 on p.id=pm7.post_id and pm7.meta_key='_sale_price_dates_to'
            where p.id in (".implode(',',$products).")
            order by ID ASC", OBJECT_K);

        $images =  $wpdb->get_results("select id,guid from ".$wpdb->prefix."posts where post_type='attachment' order by id", OBJECT_K);
        // get parents list
        $parents = array_map(create_function('$o', 'return $o->parent_id;'), $as_is_data);
        $parents = array_values(array_unique($parents));

        foreach ($products as $p_id){
            $is_simple = $is_parent = $is_variation = false;
            $p = (object) array_merge( (array)$basic[$p_id],(array)$as_is_data[$p_id]);

            if (!$p->parent_id){
                unset($p->parent_id);
                if (in_array($p_id,$parents)){
                    $is_parent = true;
                } else $is_simple = true;
            } else {
                $is_variation = true;
            }

            if ($is_variation && (empty($p->price) || is_null($p->price))){
                continue;
            }

            $p->centralfeed_name = $this->blog_name;
            $p->options = new stdClass();
            if ($is_variation){
                $p->options->description = $data[$p->parent_id]->options->description;
                $p->options->short_description = $data[$p->parent_id]->options->short_description;
            } else {
                $p->options->description = $more_data[$p_id]->description;
                $p->options->short_description = $more_data[$p_id]->short_description;
            }

            if (!empty($more_data[$p_id]->special_price) &&!is_null($more_data[$p_id]->special_price)){
                $p->options->special_price = $more_data[$p_id]->special_price;
            }
            if ($is_parent && !empty($more_data[$p_id]->variation_price)){
                $p->price = $more_data[$p_id]->variation_price;
                if (!is_null($more_data[$p_id]->variation_sale_price)){
                    $p->options->special_price = $more_data[$p_id]->variation_sale_price;
                }
            }

	        if (!empty($more_data[$p_id]->special_from_date) &&!is_null($more_data[$p_id]->special_from_date)){
		        $date = date_create();
		        date_timestamp_set($date, $more_data[$p_id]->special_from_date);

		        $p->options->special_from_date =  date_format($date, 'Y-m-d') . ' 00:00:00';
	        }

	        if (!empty($more_data[$p_id]->special_to_date) &&!is_null($more_data[$p_id]->special_to_date)){
		        $date = date_create();
		        date_timestamp_set($date, $more_data[$p_id]->special_to_date);

		        $p->options->special_to_date = date_format($date, 'Y-m-d') . ' 00:00:00';
	        }

            $p->primary_image_url = $images[$more_data[$p_id]->primary_image]->guid;
            if (is_null($p->primary_image_url) && $is_variation){
                $p->primary_image_url =  $data[$p->parent_id]->primary_image_url;
            }
            $p->additional_image_url = array();
            $p->additional_image_url[] = $p->primary_image_url;
            $gallery_images = explode(',',$more_data[$p_id]->image_gallery);
            foreach ($gallery_images as $image){
                if (!is_null($images[$image]->guid)){
                    $p->additional_image_url[] = $images[$image]->guid;
                }
            }

            if ($is_variation){
                $p->categories = $this->centralfeed_get_product_categories_formatted($p->parent_id);
            } else {
                $p->categories = $this->centralfeed_get_product_categories_formatted($p_id);
            }


            if ($is_parent){
                $p->variations = $this->centralfeed_get_product_parent_variations($p_id);
            }
            else if ($is_variation){
                $p->variations = $this->centralfeed_get_product_variant_variations($p_id,$p->parent_id);
            }

            if (!$is_variation) {
                if (isset($this->custom_options[$p_id])){
                    $p->custom_options = $this->custom_options[$p_id];
                }
                if (isset($this->attributes[$p_id])) {
                    $p->attributes = $this->attributes[$p_id];
                }
            }

            $data[$p_id] = $p;
            unset ( $basic[$p_id], $more_data[ $p_id],$p);
            gc_collect_cycles();
        }

     //   $product->tag = $this->centralfeed_get_tags($product_id);

        unset($products, $basic,$more_data,$images);
        gc_collect_cycles();
        return $data;
    }


    /**
     * @return array
     */
    protected function centralfeed_get_all_categories()
    {
        global $wpdb;
        $product_categories = $wpdb->get_results("select tax.term_id as 'id', terms.name, tax.parent
            from ".$wpdb->prefix."term_taxonomy  tax
             join ".$wpdb->prefix."terms terms on terms.term_id = tax.term_id
where tax.taxonomy = 'product_cat'
",OBJECT_K);
        $this->categories = json_decode(json_encode($product_categories), true);

        foreach($this->categories as $id=>$category_data){
            $this->categories[$id]['path'] = $this->centralfeed_get_category_path($id);
        }

        return $this->categories;
    }

    protected function centralfeed_get_all_products_categories(){
        global $wpdb;
        $this->products_categories = array();
        $rows = $wpdb->get_results("SELECT DISTINCT p.ID as post_id, t.term_id as cat_id
FROM ".$wpdb->prefix."posts p
LEFT JOIN ".$wpdb->prefix."term_relationships rel ON rel.object_id = p.ID
LEFT JOIN ".$wpdb->prefix."term_taxonomy tax ON tax.term_taxonomy_id = rel.term_taxonomy_id
LEFT JOIN ".$wpdb->prefix."terms t ON t.term_id = tax.term_id
where p.post_type like '%product%' and tax.taxonomy='product_cat'
order by p.id");

        foreach ($rows as $row) {
            if (!isset($this->products_categories[$row->post_id])){
                $this->products_categories[$row->post_id] = array();
            }
            $this->products_categories[$row->post_id][] = $row->cat_id;
        }

    }

    /**
     * @param  int $product_id
     * @return string
     * /
    protected function centralfeed_get_tags($product_id)
    {

        $string = array();
        $terms = get_the_terms($product_id, 'product_tag');
        if (is_array($terms)) {
            foreach ($terms as $term) {
                $string[] = $term->name;
            }
        }
        return $string;
    }
*/
    /**
     * @param  int $product_id
     * @return string
     */
    protected function centralfeed_get_product_categories_formatted($product_id)
    {
        $all_cat = array();
        $strings = array();

        $categories =  $this->products_categories[$product_id];

        if ($categories) {

            foreach ($categories as $category) {
                $cat_path = $this->categories[$category]['path'];
                $all_cat = array_merge($all_cat, explode('/', $cat_path));
            }
            $all_cat = array_unique($all_cat);

            foreach ($all_cat as $cat) {
                if ($cat == 0) {
                    $strings[] = array(0, "0", "Root Catalog");
                } else {
                    $strings[] = array($cat, $this->categories[$cat]['path'], $this->categories[$cat]['name']);
                }
            }
        }

        return $strings;
    }

    private function centralfeed_get_category_path($category)
    {
        if ($category ==0){
            return $category;
        }
        $cat_parent = $this->categories[$category]['parent'];
        return $this->centralfeed_get_category_path($cat_parent).'/'.$category;
    }
/*
    /**
     * @param WC_Product $_product
     * @return stdClass
     * /
    private function centralfeed_get_product_options($_product)
    {
        $p = new stdClass();

        $product = $_product->get_post_data();
        $p->description = $product->post_content;
        $p->short_description = $product->post_excerpt;

        $sale_price = $_product->get_sale_price();
        if (!empty($sale_price)){
            $p->special_price = $sale_price;

        }

        return $p;
    }

    /**
     *
     * @param WC_Product $_product
     * @return stdClass
     * /
    private function centralfeed_get_product_custom_options($_product)
    {
        $_product = wc_get_product($_product);
        return $this->centralfeed_get_product_attributes_filtered($_product,false);
    }

    /**
     *
     * @param WC_Product $_product
     * @return stdClass
     * /
    private function centralfeed_get_product_attributes($_product)
    {
        $_product = wc_get_product($_product);
        return $this->centralfeed_get_product_attributes_filtered($_product,true);
    }

    /**
     *
     * @param WC_Product $_product
     * @return stdClass
     * /
    private function centralfeed_get_product_attributes_filtered($_product,$visible)
    {
        $p = array();

        $parent_id = $_product->get_parent_id();
        if ($parent_id){
            $_product = wc_get_product($parent_id);
        }

        $attributes = $_product->get_attributes();
        foreach ($attributes as $attr=>$attr_obj){
            /*
             * @var $attr_obj WC_Product_Attribute
             * /
            if (($attr_obj['is_visible'] == $visible) && !$attr_obj['variation'] ){
                $p[urldecode($attr_obj['name'])] = $_product->get_attribute($attr);
            }
        }

        return $p;
    }
*/
    /**
     * @param int $_product
     * @return stdClass
     */
/*    private function centralfeed_get_product_variations($_product)
    {
        /*
         * @var WC_Product_Variation $_product
         * /
        $_product = wc_get_product($_product);
        $p = array();

        $attributes = $_product->get_variation_attributes();

        foreach ($attributes as $attr=>$value){
            $attr_name = wc_attribute_label(str_replace( 'attribute_', '', $attr ),$_product);

            $attr_name = urldecode($attr_name);

            if (get_class($_product) ==  "WC_Product_Variation"){
                $p[$attr_name] =  $value;
            } else {
                $p[$attr_name] = "";
            }
        }

        return $p;
    }*/
    private function centralfeed_get_product_parent_variations($_product)
    {
        $attrs = array();
        foreach($this->variations[$_product] as $slug=>$attribute){
            $attrs[$attribute] = '';
        }

        return $attrs;
    }
    private function centralfeed_get_product_variant_variations($_product,$parent)
    {
        $attrs= array();
        foreach($this->variations[$parent] as $slug=>$attribute){
            if (isset($this->attribute_values[$_product][$slug])){
              //  $attrs[$attribute] = $this->attribute_values[$_product][$slug];
            	$attrs[$attribute] = urldecode($this->attribute_values[$_product][$slug]);
            }
        }

        return $attrs;
    }


/*    /**
     * @param $_product int
     * @return string
     * /
    private function centralfeed_get_primary_image($_product)
    {
        $_product = wc_get_product($_product);
        return wp_get_attachment_url($_product->get_image_id() );
    }

    /**
     * @param $_product int
     * @return array
     * /
    private function centralfeed_get_gallery_images($_product)
    {
        $_product = wc_get_product($_product);
        $urls = array();
        $urls[] =  $this->centralfeed_get_primary_image($_product);

        $images = $_product->get_gallery_attachment_ids();
        foreach ($images as $image) {
            $urls[] = wp_get_attachment_url($image );
        }
        return $urls;
    }*/

    /* @param  array $products
     * @return object
     */
    private function centralfeed_get_product_basic_data($products)
    {
        global $wpdb;
        $data = array();

        $results = $wpdb->get_results("select p.id, p.id as entity_id, pm2.meta_value as sku, pm3.meta_value as qty , pm1.meta_value as stock_status from ".$wpdb->prefix."posts p
left join ".$wpdb->prefix."postmeta pm1 on p.id=pm1.post_id and pm1.meta_key='_stock_status'
 left join ".$wpdb->prefix."postmeta pm2 on p.id=pm2.post_id and pm2.meta_key='_sku'
left join ".$wpdb->prefix."postmeta pm3 on p.id=pm3.post_id and pm3.meta_key='_stock'
where p.id in (".implode(',',$products).")
order by ID ASC", OBJECT_K);

        foreach ($results as $key=>$row) {
            if (empty($row->sku)) unset($row->sku);

            if ($row->stock_status == 'outofstock') {
                $row->stock_status = 0;
            }
            else {
                $row->stock_status = 1;
            }

            if (is_null($row->qty)) $row->qty = '';

            $data[$key] = $row;
        }

        unset($results);
        gc_collect_cycles();

        return $data;
    }

    private function centralfeed_get_all_attributes()
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT	* FROM 	".$wpdb->prefix."postmeta WHERE meta_key like '%attr%' ");
        $names = $wpdb->get_results("SELECT	attribute_name,attribute_label FROM ".$wpdb->prefix."woocommerce_attribute_taxonomies",OBJECT_K);
        $terms = $this->centralfeed_get_all_terms();

        $attributes = array();
        foreach ($rows as $row) {
            switch ($row->meta_key){
                case '_default_attributes':
                    break;
                case '_product_attributes':
                    $attributes[$row->post_id] = unserialize($row->meta_value);
                    break;
                default:
                    if (!isset($this->attribute_values[$row->post_id])){
                        $this->attribute_values[$row->post_id] = array();
                    }

                    $this->attribute_values[$row->post_id][str_replace( 'attribute_', '', $row->meta_key )] = $row->meta_value;
                    break;
            }
        }

        foreach ($attributes as $product_id=>$attr_array){

            foreach ($attr_array as $slug=>$curr_attr) {
                $name =  $curr_attr['name'];
                $value =  $curr_attr['value'];
                if (0 === strpos($name,'pa_')){
                    $name =  substr($name,3);
                }
                if ($curr_attr['is_taxonomy']){
                    $name = $names[$name]->attribute_label;
                    $value = $terms[$product_id][$curr_attr['name']];
                }
                else {
                    $value =  array($value );
                }

                if ($curr_attr['is_variation']){
                    $this->variations[$product_id][$slug] = $name;
                }
                else if ($curr_attr['is_visible']){
                    $this->attributes[$product_id][$name] = $value;
                } else {
                    $this->custom_options[$product_id][$name] = $value;
                }
            }

        }

        unset($rows,$names,$attributes,$terms);
        gc_collect_cycles();
    }

    /**
     * @param $wpdb
     */
    private function centralfeed_get_all_terms()
    {
        global $wpdb;
        $terms_unsorted = $wpdb->get_results("SELECT tr.object_id AS product_id,
x.taxonomy, t.name AS term_name
FROM " . $wpdb->prefix . "term_relationships AS tr
INNER JOIN  " . $wpdb->prefix . "term_taxonomy AS x
ON (x.taxonomy like 'pa_%'
AND x.term_taxonomy_id=tr.term_taxonomy_id)
INNER JOIN  " . $wpdb->prefix . "terms AS t

ON t.term_id=x.term_id
order by tr.object_id", ARRAY_A);


        $terms = array();

        foreach ($terms_unsorted as $key => $item) {
            $terms[$item['product_id']][$item['taxonomy']][] = $item['term_name'];
        }
        return $terms;
    }

}
