<?php
/**
 * Module d'importation des produits et catégories Victron Energy pour PrestaShop
 * Version 3.3.0 : Simplification pour importer uniquement dans le catalogue, sans affichage en page d'accueil.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_VictronProducts extends Module
{
    private $manufacturerId = null;
    private $victronParentCategoryId = null;
    private $defaultLanguageId;
    private $lastError = null;
    private $productPrefix = 'VIC-';

    public function __construct()
    {
        $this->name = 'ps_victronproducts';
        $this->tab = 'migration_tools';
        $this->version = '3.3.0';
        $this->author = 'Votre Nom';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Importateur de produits Victron');
        $this->description = $this->l('Importe et met à jour les produits Victron Energy, leurs catégories et leurs images directement dans votre catalogue.');
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->defaultLanguageId = (int)Configuration::get('PS_LANG_DEFAULT');
    }

    public function install()
    {
        return parent::install() &&
            Configuration::updateValue('VICTRON_API_KEY', '') &&
            Configuration::updateValue('VICTRON_LAST_SYNC', 0);
    }

    public function uninstall()
    {
        Configuration::deleteByName('VICTRON_API_KEY');
        Configuration::deleteByName('VICTRON_LAST_SYNC');
        Configuration::deleteByName('VICTRON_PARENT_CATEGORY');
        return parent::uninstall();
    }

    public function getContent()
    {
        $output = '';

        if (!file_exists(__DIR__ . '/cacert.pem')) {
            $output .= $this->displayWarning($this->l('Le fichier cacert.pem est manquant. La connexion à l\'API et le téléchargement des images pourraient échouer.'));
        }

        if (Tools::isSubmit('submitConfig')) {
            Configuration::updateValue('VICTRON_API_KEY', Tools::getValue('VICTRON_API_KEY'));
            $output .= $this->displayConfirmation($this->l('Paramètres mis à jour avec succès.'));
        } elseif (Tools::isSubmit('runSync')) {
            @set_time_limit(3000);
            @ini_set('memory_limit', '1024M');
            $result = $this->runSync();
            if ($result) {
                $output .= $this->displayConfirmation($this->l('Synchronisation terminée avec succès.'));
            } else {
                $output .= $this->displayError($this->l('La synchronisation a échoué : ') . $this->lastError);
            }
        } elseif (Tools::isSubmit('clearProducts')) {
            $deletedCount = $this->clearVictronProducts();
            if ($deletedCount !== false) {
                 $output .= $this->displayConfirmation(sprintf($this->l('%d produits Victron ont été supprimés avec succès.'), $deletedCount));
            } else {
                $output .= $this->displayError($this->l('Une erreur est survenue lors de la suppression des produits.'));
            }
        }

        $lastSync = (int)Configuration::get('VICTRON_LAST_SYNC');
        if (!$lastSync) {
            $output .= $this->displayWarning($this->l('Aucune synchronisation n\'a encore été effectuée.'));
        } else {
            $output .= $this->displayInformation(sprintf($this->l('Dernière synchronisation effectuée le : %s'), date('d/m/Y H:i:s', $lastSync)));
        }

        return $output . $this->renderForm();
    }

    private function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => ['title' => $this->l('Configuration de l\'API Victron'), 'icon' => 'icon-cogs'],
                'input' => [['type' => 'text', 'label' => $this->l('Clé API Victron E-Order'), 'name' => 'VICTRON_API_KEY', 'required' => true]],
                'submit' => ['title' => $this->l('Enregistrer'), 'name' => 'submitConfig'],
                'buttons' => [
                    ['title' => $this->l('Lancer la Synchronisation Complète'), 'name' => 'runSync', 'type' => 'submit', 'class' => 'btn btn-primary pull-right', 'icon' => 'process-icon-refresh'],
                    ['title' => $this->l('Nettoyer les produits Victron'), 'name' => 'clearProducts', 'type' => 'submit', 'class' => 'btn btn-danger', 'icon' => 'process-icon-delete', 'confirm' => $this->l('Êtes-vous sûr de vouloir supprimer tous les produits dont la référence commence par "VIC-" ? Cette action est irréversible.')]
                ]
            ]
        ];
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->fields_value['VICTRON_API_KEY'] = Configuration::get('VICTRON_API_KEY');
        return $helper->generateForm([$fields_form]);
    }
    
    private function fetchApiData($endpoint, $apiKey)
    {
        $baseUrl = 'https://eorder.victronenergy.com';
        $allData = [];
        $nextUrl = $baseUrl . $endpoint . '?format=json';

        while ($nextUrl) {
            $ch = curl_init();
            $options = [
                CURLOPT_URL => $nextUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_SSL_VERIFYPEER => file_exists(__DIR__ . '/cacert.pem'),
                CURLOPT_CAINFO => __DIR__ . '/cacert.pem',
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_HTTPHEADER => ['Authorization: ' . $apiKey, 'Accept: application/json'],
                CURLOPT_USERAGENT => 'PrestaShop-Module/1.0',
            ];
            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
            
            if ($response === false) {
                $this->lastError = "Erreur cURL : " . curl_error($ch);
                curl_close($ch);
                return false;
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                $this->lastError = "Erreur HTTP : $httpCode.";
                return false;
            }

            $data = json_decode($response, true);
            if (isset($data['results']) && is_array($data['results'])) {
                $allData = array_merge($allData, $data['results']);
                $nextUrl = $data['next'] ?? null;
            } else {
                $allData = is_array($data) ? $data : [];
                $nextUrl = null;
            }
        }
        return $allData;
    }

    private function setupEnvironment()
    {
        $this->manufacturerId = Manufacturer::getIdByName('Victron Energy');
        if (!$this->manufacturerId) {
            $manufacturer = new Manufacturer();
            $manufacturer->name = 'Victron Energy';
            if ($manufacturer->add()) {
                $this->manufacturerId = $manufacturer->id;
            }
        }

        $this->victronParentCategoryId = (int)Configuration::get('VICTRON_PARENT_CATEGORY');
        if (!$this->victronParentCategoryId || !Validate::isLoadedObject(new Category($this->victronParentCategoryId))) {
            $category = new Category();
            $category->name = array_fill_keys(Language::getIDs(true), 'Produits Victron Energy');
            $category->link_rewrite = array_fill_keys(Language::getIDs(true), Tools::str2url('produits-victron-energy'));
            $category->id_parent = (int)Configuration::get('PS_HOME_CATEGORY');
            $category->active = true;
            if ($category->add()) {
                $this->victronParentCategoryId = $category->id;
                Configuration::updateValue('VICTRON_PARENT_CATEGORY', $this->victronParentCategoryId);
            }
        }
        return true;
    }

    public function runSync()
    {
        if (!$this->setupEnvironment()) return false;

        $apiKey = Configuration::get('VICTRON_API_KEY');
        if (empty($apiKey)) {
            $this->lastError = $this->l('La clé API n\'est pas configurée.');
            return false;
        }
        
        $apiCategories = $this->fetchApiData('/api/v1/categories/', $apiKey);
        $productsData = $this->fetchApiData('/api/v1/products-extended/', $apiKey);
        
        if ($productsData === false || $apiCategories === false) {
            return false;
        }
        
        $categoriesMap = $this->processCategories($apiCategories);

        foreach ($productsData as $productData) {
            $this->importProduct($productData, $categoriesMap);
        }
        
        Configuration::updateValue('VICTRON_LAST_SYNC', time());
        return true;
    }

    private function processCategories(array $apiCategories)
    {
        $categoriesMap = [];
        foreach ($apiCategories as $categoryData) {
            $categoryName = trim($categoryData['name']);
            if (empty($categoryName)) continue;
            
            $id_category = (int)Db::getInstance()->getValue('
                SELECT c.id_category FROM `'._DB_PREFIX_.'category` c
                LEFT JOIN `'._DB_PREFIX_.'category_lang` cl ON (c.id_category = cl.id_category AND cl.id_lang = '.(int)$this->defaultLanguageId.')
                WHERE cl.name = "'.pSQL($categoryName).'" AND c.id_parent = '.(int)$this->victronParentCategoryId
            );
            
            if (!$id_category) {
                $category = new Category();
                $category->name = array_fill_keys(Language::getIDs(true), $categoryName);
                $category->link_rewrite = array_fill_keys(Language::getIDs(true), Tools::str2url($categoryName));
                $category->id_parent = $this->victronParentCategoryId;
                $category->active = true;
                if ($category->add()) {
                    $id_category = $category->id;
                }
            }
            if ($id_category) {
                $categoriesMap[$categoryName] = $id_category;
                if (!empty($categoryData['image'])) {
                     $this->importCategoryImage($id_category, $categoryData['image']);
                }
            }
        }
        return $categoriesMap;
    }
    
    private function importProduct($productData, $categoriesMap)
    {
        if (empty($productData['sku'])) return;
        $reference = $this->productPrefix . $productData['sku'];

        $productId = (int)Product::getIdByReference($reference);
        $product = new Product($productId ?: null);
        
        foreach (Language::getIDs(true) as $id_lang) {
            $product->name[$id_lang] = $productData['product_data']['name'] ?? 'Produit Victron sans nom';
            $product->description[$id_lang] = $productData['product_data']['description'] ?? '';
            $product->description_short[$id_lang] = Tools::truncateString(strip_tags($product->description[$id_lang]), 400);
            $product->link_rewrite[$id_lang] = Tools::str2url($product->name[$id_lang]);
        }
        
        $product->reference = $reference;
        $product->price = (float)($productData['price'] ?? 0);
        $product->id_manufacturer = $this->manufacturerId;
        
        $categoryName = $productData['category'] ?? '';
        $id_category = $categoriesMap[$categoryName] ?? $this->victronParentCategoryId;
        $product->id_category_default = $id_category;
        
        $product->active = true;
        $product->state = 1;

        try {
            $product->save();
            $product->updateCategories(array_unique([$this->victronParentCategoryId, $id_category]));
            StockAvailable::setQuantity($product->id, 0, 100);

            if (!Image::hasImages($this->defaultLanguageId, $product->id) && !empty($productData['product_data']['image'])) {
                $this->importProductImage($product, $productData['product_data']['image']);
            }
        } catch (Exception $e) {
            $this->lastError = 'Erreur produit ' . $reference . ': ' . $e->getMessage();
        }
    }
    
    private function importProductImage($product, $imageUrl)
    {
        $image = new Image();
        $image->id_product = (int)$product->id;
        $image->position = Image::getHighestPosition($product->id) + 1;
        $image->cover = true;
        
        if ($image->add()) {
            $this->copyImg($product->id, $image->id, $imageUrl, 'products');
        }
    }

    private function importCategoryImage($categoryId, $imageUrl)
    {
        $category = new Category($categoryId);
        if (Validate::isLoadedObject($category) && !$category->id_image) {
            $this->copyImg($categoryId, null, $imageUrl, 'categories');
        }
    }

    protected function copyImg($id_entity, $id_image = null, $url, $entity = 'products')
    {
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        if (!$tmpfile) return;

        $urlParts = explode('/', $url);
        $fileName = array_pop($urlParts);
        $encodedFileName = rawurlencode($fileName);
        $encodedUrl = implode('/', $urlParts) . '/' . $encodedFileName;

        if (Tools::copy($encodedUrl, $tmpfile)) {
            if ($entity === 'categories') {
                $path = _PS_CAT_IMG_DIR_ . (int)$id_entity;
                ImageManager::resize($tmpfile, $path . '.jpg');
            } else { // products
                $path = (new Image($id_image))->getPathForCreation();
                ImageManager::resize($tmpfile, $path . '.jpg');
                $types = ImageType::getImagesTypes('products');
                foreach ($types as $image_type) {
                    ImageManager::resize($path . '.jpg', $path . '-' . stripslashes($image_type['name']) . '.jpg', $image_type['width'], $image_type['height']);
                }
            }
        }
        @unlink($tmpfile);
    }

    private function clearVictronProducts()
    {
        $productIds = Db::getInstance()->executeS(
            'SELECT id_product FROM `'._DB_PREFIX_.'product` WHERE reference LIKE "'.pSQL($this->productPrefix).'%"'
        );

        if (empty($productIds)) {
            return 0;
        }
        
        $count = 0;
        foreach ($productIds as $row) {
            $product = new Product((int)$row['id_product']);
            if (Validate::isLoadedObject($product) && $product->delete()) {
                $count++;
            }
        }
        return $count;
    }
}