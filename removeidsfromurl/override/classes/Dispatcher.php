<?php
/**
 * Override Dispatcher per il modulo removeidsfromurl.
 */
class Dispatcher extends DispatcherCore
{
    public function getController($id_shop = null)
    {
        if (defined('_PS_ADMIN_DIR_') || $this->controller || !Module::isEnabled('removeidsfromurl')) {
            return parent::getController($id_shop);
        }

        // 1) Vecchi URL con ID -> 301 verso il nuovo URL
        $this->ridRedirectOldUrl();

        // 2) Risoluzione normale delle route (ora senza {id})
        $controller = parent::getController($id_shop);

        if (empty($_GET['rewrite']) || !is_string($_GET['rewrite'])) {
            return $controller;
        }

        $rewrite = $_GET['rewrite'];
        $idLang = (int) Context::getContext()->language->id;
        $idShop = (int) Context::getContext()->shop->id;
        list($uri) = explode('?', (string) $this->request_uri);

        $found = null; // null = non applicabile, true/false = esito ricerca

        switch ($controller) {
            case 'product':
                if (empty($_GET['id_product'])) {
                    $id = $this->ridFindByRewrite('product_lang', 'product_shop', 'id_product', $rewrite, $idLang, $idShop);
                    $found = (bool) $id;
                    if ($id) {
                        $_GET['id_product'] = $id;
                    }
                }
                break;

            case 'category':
                if (empty($_GET['id_category'])) {
                    $id = $this->ridFindByRewrite('category_lang', 'category_shop', 'id_category', $rewrite, $idLang, $idShop);
                    $found = (bool) $id;
                    if ($id) {
                        $_GET['id_category'] = $id;
                    }
                }
                break;

            case 'cms':
                if (empty($_GET['id_cms']) && empty($_GET['id_cms_category'])) {
                    if (strpos($uri, '/content/category/') === 0) {
                        $id = $this->ridFindByRewrite('cms_category_lang', 'cms_category_shop', 'id_cms_category', $rewrite, $idLang, $idShop);
                        if ($id) {
                            $_GET['id_cms_category'] = $id;
                        }
                    } else {
                        $id = $this->ridFindByRewrite('cms_lang', 'cms_shop', 'id_cms', $rewrite, $idLang, $idShop);
                        if ($id) {
                            $_GET['id_cms'] = $id;
                        }
                    }
                    $found = (bool) $id;
                }
                break;

            case 'manufacturer':
                if (empty($_GET['id_manufacturer'])) {
                    $id = $this->ridFindByName('manufacturer', 'id_manufacturer', $rewrite);
                    $found = (bool) $id;
                    if ($id) {
                        $_GET['id_manufacturer'] = $id;
                    }
                }
                break;

            case 'supplier':
                if (empty($_GET['id_supplier'])) {
                    $id = $this->ridFindByName('supplier', 'id_supplier', $rewrite);
                    $found = (bool) $id;
                    if ($id) {
                        $_GET['id_supplier'] = $id;
                    }
                }
                break;
        }

        // Elemento non trovato: pagina 404
        if ($found === false) {
            $controller = $this->controller_not_found;
            $_GET['controller'] = $controller;
            $this->controller = $controller;
        }

        return $controller;
    }

    /**
     * Cerca un ID tramite link_rewrite (tabelle *_lang). Preferisce elementi attivi/visibili,
     * poi l'ID più basso.
     */
    protected function ridFindByRewrite($langTable, $shopTable, $idCol, $rewrite, $idLang, $idShop)
    {
        $sql = 'SELECT l.`' . bqSQL($idCol) . '`
                FROM `' . _DB_PREFIX_ . bqSQL($langTable) . '` l
                WHERE l.link_rewrite = \'' . pSQL($rewrite) . '\'
                  AND l.id_lang = ' . (int) $idLang . '
                  AND l.id_shop = ' . (int) $idShop . '
                ORDER BY l.`' . bqSQL($idCol) . '` ASC';

        return (int) Db::getInstance()->getValue($sql);
    }

    /**
     * Marche e fornitori non hanno link_rewrite: viene derivato dal nome.
     */
    protected function ridFindByName($table, $idCol, $rewrite)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `' . bqSQL($idCol) . '` AS id, name FROM `' . _DB_PREFIX_ . bqSQL($table) . '` ORDER BY `' . bqSQL($idCol) . '` ASC'
        );
        if ($rows) {
            foreach ($rows as $row) {
                if (Tools::str2url($row['name']) === $rewrite) {
                    return (int) $row['id'];
                }
            }
        }

        return 0;
    }

    /**
     * Se l'URL richiesto è nel vecchio formato con ID, reindirizza (301) al nuovo.
     * Controlla che il rewrite coincida con quello dell'elemento, per non
     * confondere URL nuovi che iniziano con dei numeri (es. 500-ml-bottiglia).
     */
    protected function ridRedirectOldUrl()
    {
        if (!$this->use_routes || empty($this->request_uri)) {
            return;
        }
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'GET') {
            return;
        }

        list($uri) = explode('?', (string) $this->request_uri);
        $idLang = (int) Context::getContext()->language->id;
        $idShop = (int) Context::getContext()->shop->id;
        $link = new Link();
        $target = null;
        $m = [];

        if (preg_match('#^/(?:[^/]+/)*(\d+)-([^/]+)\.html$#', $uri, $m)) {
            $id = (int) $m[1];
            $rest = $m[2];
            $candidates = [$rest, preg_replace('/^\d+-/', '', $rest)];
            $rw = Db::getInstance()->getValue(
                'SELECT link_rewrite FROM `' . _DB_PREFIX_ . 'product_lang`
                 WHERE id_product = ' . $id . ' AND id_lang = ' . $idLang . ' AND id_shop = ' . $idShop
            );
            if ($rw && in_array($rw, $candidates, true)) {
                $target = $link->getProductLink($id, null, null, null, $idLang, $idShop);
            }
        } elseif (preg_match('#^/brand/(\d+)-([^/]+)$#', $uri, $m)) {
            $name = Db::getInstance()->getValue('SELECT name FROM `' . _DB_PREFIX_ . 'manufacturer` WHERE id_manufacturer = ' . (int) $m[1]);
            if ($name && Tools::str2url($name) === $m[2]) {
                $target = $link->getManufacturerLink((int) $m[1], null, $idLang);
            }
        } elseif (preg_match('#^/supplier/(\d+)-([^/]+)$#', $uri, $m)) {
            $name = Db::getInstance()->getValue('SELECT name FROM `' . _DB_PREFIX_ . 'supplier` WHERE id_supplier = ' . (int) $m[1]);
            if ($name && Tools::str2url($name) === $m[2]) {
                $target = $link->getSupplierLink((int) $m[1], null, $idLang);
            }
        } elseif (preg_match('#^/content/category/(\d+)-([^/]+)$#', $uri, $m)) {
            $rw = Db::getInstance()->getValue(
                'SELECT link_rewrite FROM `' . _DB_PREFIX_ . 'cms_category_lang`
                 WHERE id_cms_category = ' . (int) $m[1] . ' AND id_lang = ' . $idLang . ' AND id_shop = ' . $idShop
            );
            if ($rw && $rw === $m[2]) {
                $target = $link->getCMSCategoryLink((int) $m[1], null, $idLang);
            }
        } elseif (preg_match('#^/content/(\d+)-([^/]+)$#', $uri, $m)) {
            $rw = Db::getInstance()->getValue(
                'SELECT link_rewrite FROM `' . _DB_PREFIX_ . 'cms_lang`
                 WHERE id_cms = ' . (int) $m[1] . ' AND id_lang = ' . $idLang . ' AND id_shop = ' . $idShop
            );
            if ($rw && $rw === $m[2]) {
                $target = $link->getCMSLink((int) $m[1], null, null, $idLang);
            }
        } elseif (preg_match('#^/(\d+)-([^/.]+)$#', $uri, $m)) {
            $rw = Db::getInstance()->getValue(
                'SELECT link_rewrite FROM `' . _DB_PREFIX_ . 'category_lang`
                 WHERE id_category = ' . (int) $m[1] . ' AND id_lang = ' . $idLang . ' AND id_shop = ' . $idShop
            );
            if ($rw && $rw === $m[2]) {
                $target = $link->getCategoryLink((int) $m[1], null, $idLang);
            }
        }

        if (!$target) {
            return;
        }

        // Evita loop: non reindirizzare se il percorso di destinazione è lo stesso
        $targetPath = parse_url($target, PHP_URL_PATH);
        $currentPath = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if ($targetPath === $currentPath) {
            return;
        }

        if (!empty($_SERVER['QUERY_STRING'])) {
            $target .= (strpos($target, '?') === false ? '?' : '&') . $_SERVER['QUERY_STRING'];
        }

        header('HTTP/1.1 301 Moved Permanently');
        header('Cache-Control: no-cache');
        header('Location: ' . $target);
        exit;
    }
}
