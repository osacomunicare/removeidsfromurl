<?php
/**
 * Remove IDs from URL - rimuove gli ID numerici dagli URL di prodotti,
 * categorie, marche, fornitori e pagine CMS.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Removeidsfromurl extends Module
{
    /** Route senza {id} */
    private static $newRoutes = [
        'PS_ROUTE_product_rule'      => '{rewrite}.html',
        'PS_ROUTE_category_rule'     => '{rewrite}',
        'PS_ROUTE_supplier_rule'     => 'supplier/{rewrite}',
        'PS_ROUTE_manufacturer_rule' => 'brand/{rewrite}',
        'PS_ROUTE_cms_rule'          => 'content/{rewrite}',
        'PS_ROUTE_cms_category_rule' => 'content/category/{rewrite}',
    ];

    public function __construct()
    {
        $this->name = 'removeidsfromurl';
        $this->tab = 'seo';
        $this->version = '1.0.0';
        $this->author = 'Custom';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('Remove IDs from URL');
        $this->description = $this->l('Rimuove gli ID numerici dagli URL e reindirizza (301) i vecchi URL.');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        // Backup delle route attuali, per ripristinarle in disinstallazione
        $backup = [];
        foreach (array_keys(self::$newRoutes) as $key) {
            $backup[$key] = Configuration::get($key);
        }
        Configuration::updateGlobalValue('RIDU_ROUTES_BACKUP', json_encode($backup));

        // Scrittura diretta: il back office rifiuterebbe regole senza {id}
        foreach (self::$newRoutes as $key => $rule) {
            Configuration::updateGlobalValue($key, $rule);
        }

        Tools::clearSmartyCache();
        Tools::clearCache();
        Tools::generateIndex();

        return true;
    }

    public function uninstall()
    {
        $backup = json_decode((string) Configuration::getGlobalValue('RIDU_ROUTES_BACKUP'), true);
        $defaults = [
            'PS_ROUTE_product_rule'      => '{id}{-:id_product_attribute}-{rewrite}.html',
            'PS_ROUTE_category_rule'     => '{id}-{rewrite}',
            'PS_ROUTE_supplier_rule'     => 'supplier/{id}-{rewrite}',
            'PS_ROUTE_manufacturer_rule' => 'brand/{id}-{rewrite}',
            'PS_ROUTE_cms_rule'          => 'content/{id}-{rewrite}',
            'PS_ROUTE_cms_category_rule' => 'content/category/{id}-{rewrite}',
        ];
        foreach ($defaults as $key => $default) {
            $value = (is_array($backup) && !empty($backup[$key])) ? $backup[$key] : $default;
            Configuration::updateGlobalValue($key, $value);
        }
        Configuration::deleteByName('RIDU_ROUTES_BACKUP');

        $ok = parent::uninstall();

        Tools::clearSmartyCache();
        Tools::clearCache();
        Tools::generateIndex();

        return $ok;
    }

    /**
     * Pagina di configurazione: segnala i rewrite duplicati,
     * che senza ID entrerebbero in conflitto.
     */
    public function getContent()
    {
        $html = '<div class="panel"><h3>' . $this->l('Controllo rewrite duplicati') . '</h3>';
        $html .= '<p>' . $this->l('Senza ID, due elementi con lo stesso rewrite (URL semplificato) nella stessa lingua e negozio non sono distinguibili: viene mostrato sempre il primo. Correggili dalla scheda SEO.') . '</p>';

        $checks = [
            'Prodotti'  => ['product_lang', 'id_product'],
            'Categorie' => ['category_lang', 'id_category'],
            'Pagine CMS' => ['cms_lang', 'id_cms'],
            'Categorie CMS' => ['cms_category_lang', 'id_cms_category'],
        ];

        $found = false;
        foreach ($checks as $label => $info) {
            list($table, $idCol) = $info;
            $rows = Db::getInstance()->executeS(
                'SELECT link_rewrite, id_lang, id_shop, COUNT(*) AS c, GROUP_CONCAT(' . bqSQL($idCol) . ') AS ids
                 FROM `' . _DB_PREFIX_ . bqSQL($table) . '`
                 GROUP BY link_rewrite, id_lang, id_shop
                 HAVING c > 1 LIMIT 100'
            );
            if ($rows) {
                $found = true;
                $html .= '<h4>' . $label . '</h4><ul>';
                foreach ($rows as $r) {
                    $html .= '<li><code>' . htmlspecialchars($r['link_rewrite']) . '</code> (lingua ' . (int) $r['id_lang']
                        . ', negozio ' . (int) $r['id_shop'] . ') &rarr; ID: ' . htmlspecialchars($r['ids']) . '</li>';
                }
                $html .= '</ul>';
            }
        }
        if (!$found) {
            $html .= '<div class="alert alert-success">' . $this->l('Nessun rewrite duplicato trovato.') . '</div>';
        }

        $html .= '<p class="help-block">' . $this->l('Nota: marche e fornitori usano un rewrite derivato dal nome; nomi uguali o molto simili vanno comunque evitati.') . '</p>';
        $html .= '</div>';

        return $html;
    }
}
