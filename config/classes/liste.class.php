<?php
/**
* Affichage de liste
*
* Affichage de liste pagin�e avec posibilit� de tri de recherches et de filtres
*
*/
class Liste
{
    /**
    * Nombres de ligne a afficher dans la liste
    * @var int
    */
    protected $nb_lignes = NBPP_DEFAULT;

    /**
    * Nombres de page a afficher dans la pagination : toujours impair
    * @var int
    */
    protected $nb_liens = NB_LIENS_DEFAULT;

    /**
    * Op�rateurs autoriser pour un filtre
    * @var array
    */
    protected $operateurs = array('=', '<', '<=', '>', '>=');

    /**
    * Op�rateur par d�fault d'un filtre
    * @var int
    */
    protected $operateur_default = '=';

    /**
    * Requ�te � effectuer
    * @var array
    */
    protected $requete;

    /**
    * Requ�tes effectu�es
    * @var varchar
    */
    protected $sql;
    protected $sql_sans_pagination;

    /**
    * Page de la liste
    * @var array
    */
    protected $page = 1;

    /**
    * Noms des inputs des filtres, sa valeur et leurs correspondances
    * @var array
    */
    protected $filtres = array();

    /**
    * Nom de l'input du champ de recherche, sa valeur et les collonnes de recherches
    * @var array
    */
    protected $recherche = array();

    /**
    * Colonne du tri et l'ordre
    * @var array
    */
    protected $tri = array();

    /**
    * Colonne du tri et l'ordre
    * @var array
    */
    protected $tri_ordre_default = 'ASC';

    /**
    * Donn�es compl�mentaires GET de l'url
    * @var array
    */
    protected $url_requete = '';

    /**
    * Nombre de r�sultats de la requete
    * @var array
    */
    protected $count = false;

    /**
    * Constructor function.
    */
    public function __construct()
    {
        $this->_recupParamsListe();
        // Modification de la page active
        if (!empty($_GET['page']) and  is_numeric($_GET['page'])) {
            $this->page = $_GET['page'];
        }
    }

    /**
    * Param�tres actuel de la liste (filtres, recherche, pagination, tri)
    * A transmettre dans toutes les urls et formulaire de la liste
    *
    * @param bool $form  affichage dans un lien (false) ou dans un formulaire (true)
    * @return varchar $params_liste
    */
    public function paramsListe($form = false)
    {
        $params = array();
        $params_liste = '';

        if (!empty($this->recherche['value'])) {
            $params['lr'] = urlencode($this->recherche['value']);
        }
        foreach ($this->filtres as $key => $filtre) {
            if (isset($filtre['value']) and $filtre['value'] != '') {
                $params['lf'][$key] = urlencode($filtre['value']);
            }
        }
        if (!empty($this->tri['colonne'])) {
            $params['ltc'] = urlencode($this->tri['colonne']);
        }
        if (!empty($this->tri['ordre'])) {
            $params['lto'] = $this->tri['ordre'];
        }
        if (!(empty($this->page) or $this->page == 1)) {
            $params['lp'] = $this->page;
        }
        if (!empty($this->url_requete)) {
            $params = $this->url_requete + $params;
        }
        foreach ($params as $key => $value) {
            if ($key == 'lf') {
                foreach ($value as $i => $v) {
                    if ($form) {
                        $params_liste .= '<input type="hidden" name="lf['.$i.']" value="'.$v.'" />';
                    }
                    else {
                        $params_liste .= '&amp;lf['.$i.']='.$v;
                    }
                }
            }
            else {
                if ($form) {
                    $params_liste .= '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
                }
                else {
                    $params_liste .= '&amp;'.$key.'='.$value;
                }
            }
        }
        /*if (!$form) {
            $params_liste = substr($params_liste, 5);
        }*/

        return $params_liste;
    }

    /**
    * R�cup�ration des param�tres de la liste (filtres, recherche, pagination, tri)
    *
    * @param varchar $_GET
    * @return bool
    */
    protected function _recupParamsListe()
    {
        $params_liste = $_GET;

        if (isset($params_liste['lr'])) {
            $this->recherche['value'] = urldecode($params_liste['lr']);
        }
        if (isset($params_liste['lf'])) {
            foreach ($params_liste['lf'] as $i => $filtre) {
                $this->filtres[$i]['value'] = urldecode($params_liste['lf'][$i]);
            }
        }
        if (isset($params_liste['lp']) and  is_numeric($params_liste['lp'])) {
            $this->page =  $params_liste['lp'];
        }
        if (isset($params_liste['ltc'])) {
            //TODO tester valeur de $params_liste['ltc']
            $this->tri['colonne'] =  urldecode($params_liste['ltc']);
        }
        if (isset($params_liste['lto']) and ($params_liste['lto'] == 'DESC' or $params_liste['lto'] = 'ASC')) {
            $this->tri['ordre'] =  $params_liste['lto'];
        }
        return ;
    }

    /**
    * Setter de filtres
    * @param array $filtres
    */
    public function setFiltres($filtres)
    {
        foreach ($filtres as $key => $filtre) {
            if (isset($_GET[$filtre['input']])) {
                $filtre['value'] = $_GET[$filtre['input']];
                $this->page = 1;
            }
            if (empty($filtre['operateur']) or !in_array($filtre['operateur'], $this->operateurs )) {
                $filtre['operateur'] = $this->operateur_default;
            }
            if (empty($filtre['type'])) {
                $filtre['type'] = false;
            }
            $this->filtres[$key]  = isset($this->filtres[$key]) ? $this->filtres[$key] : array();
            $this->filtres[$key] =  $filtre + $this->filtres[$key];
        }
    }

    /**
    * Setter de recherche
    * @param array $recherche
    */
    public function setRecherche($recherche)
    {
        if (isset($_GET[$recherche['input']])) {
            $recherche['value'] = $_GET[$recherche['input']];
            $this->page = 1;
        }
        // TODO voir pour faire un setRecherche avec un value par d�faut
        $this->recherche = $recherche + $this->recherche;
    }

    /**
    * Setter de tri
    * @param array $ordre
    */
    public function setTri($tri)
    {
        // Modification du tri
        if (!empty($_GET['tri'])) {
            if (! $this->tri) {
                $ordre = $this->tri_ordre_default;
            }
            elseif ($this->tri['ordre'] == 'DESC') {
                $ordre = 'ASC';
            }
            else {
                $ordre = 'DESC';
            }
            //TODO tester valeur de $_GET['tri']
            $this->tri = array(
                            'colonne'   => $_GET['tri'],
                            'ordre'     => $ordre
                        );
            $this->page = 1;
        }
        else {
            $this->tri = $tri + $this->tri;
        }
    }

    /**
    * Setter de nb_lignes
    * @param int $nb_lignes
    */
    public function setNbLignes($nb_lignes)
    {
        if (!empty($nb_lignes) and  is_numeric($nb_lignes)) {
            $this->nb_lignes = $nb_lignes;
        }
    }

    /**
    * Setter de nb_liens
    * @param int $nb_liens
    */
    public function setNbLiens($nb_liens)
    {
        if (!empty($nb_liens) and  is_numeric($nb_liens) and ($nb_liens%2) == 1) {
            $this->nb_liens = $nb_liens;
        }
    }

    /**
    * Setter de url_requete
    * @param array $url_requete
    */
    public function setUrlRequete($url_requete)
    {
        $this->url_requete = $url_requete;
    }

    /**
    * Getter de recherche['value']
    * @return varchar $recherche['value']
    */
    public function getRechercheValue()
    {
        return isset($this->recherche['value']) ?  $this->recherche['value'] : '';
    }

    /**
    * Getter de filtre['value']
    * @param varchar $input
    * @return varchar $recherche[x]['value']
    */
    public function getFiltreValue($input)
    {
        foreach ($this->filtres as $filtre) {
            if (isset($filtre['value']) and $filtre['value'] != '' and $filtre['input'] == $input) {
                return $filtre['value'];
            }
        }
        return false;
    }

    /**
    * Getter de tri
    * @param array $tri
    */
    public function getTri()
    {
        return $this->tri;
    }

    /**
    * Getter de page
    * @param array $page
    */
    public function getPage()
    {
        return $this->page;
    }

    /**
    * Getter de sql
    * @param array $page
    */
    public function getSql()
    {
        return $this->sql_sans_pagination;
    }

    /**
    * Ex�cution de la requ�te
    * param varchar $requete
    * @return array $lignes
    */
    public function select($requete)
    {
        GLOBAL $db;
        $sql = '';

        $this->requete = $requete;

        if (empty($this->requete['colonnes']) or empty($this->requete['from'])) {
            return false;
        }

        $sql .= ' FROM '.$this->requete['from'];
        $sql .= ' WHERE 1 = 1 ';

        if (!empty($this->requete['where'])) {
            $sql .= ' AND '.$this->requete['where'];
        }
        if (!empty($this->recherche['value'])) {
            $sql .= ' AND ( 1 != 1 ';
            foreach ($this->recherche['colonnes'] as $colonne)  {
                $sql .= ' OR '.$colonne." LIKE '%".trim($db->escape($this->recherche['value']))."%'";
            }
            $sql .= ') ';
        }
        foreach ($this->filtres as $filtre) {
            if (isset($filtre['value']) and $filtre['value'] != '') {
                if  ($filtre['type'] == 'intervallaire') {
                    $values = explode('-', $filtre['value']);
                    $colonnes = explode('-', $filtre['colonne']);
                    $sql .= " AND ".$colonnes[0]." >= '".$db->escape($values[0])."' AND ".$colonnes[1]." <= '".$db->escape($values[1])."' " ;
                }
                elseif  ($filtre['type'] == 'sous requete') {
                    $sous_requete = sprintf($filtre['requete'], $db->escape($filtre['value']));
                    $sql .= ' AND '.$filtre['colonne'].' '.$filtre['operateur']." (".$sous_requete.")" ;
                }
                else {
                    $sql .= ' AND '.$filtre['colonne'].' '.$filtre['operateur']." '".$db->escape($filtre['value'])."'" ;
                }
            }
        }
        if (!empty($this->requete['group by'])) {
            $sql .= ' GROUP BY '.$this->requete['group by'];
        }
        if (!empty($this->requete['having'])) {
            $sql .= ' HAVING '.$this->requete['having'];
        }
        if ($this->tri) {
            $sql .= ' ORDER BY '.$db->escape($this->tri['colonne']).' '.$this->tri['ordre'];
        }
        elseif (!empty($this->requete['order by'])) {
            $sql .= ' ORDER BY '.$this->requete['order by'];
        }
        $this->sql_sans_pagination = $sql;

        $de = ($this->page - 1) * $this->nb_lignes;

        $sql .= " LIMIT ".$de.",".$this->nb_lignes;

        $this->sql_sans_pagination = 'SELECT 1 '.$this->sql_sans_pagination;
        $this->sql = 'SELECT '.$this->requete['colonnes'].$sql;
        $lignes = $db->query($this->sql);

        return $lignes;
    }

    /**
    * Nombre de r�sultat de la liste
    * @return int $count
    */
    public function count()
    {
        GLOBAL $db;

        if (!$this->count) {
            $result = $db->query($this->sql_sans_pagination);
            $this->count = $db->num_rows($result);
        }
        return $this->count;
    }

    /**
    *   PAGINATION sous la forme  <<   <   x  y  z   >   >>
    *   A partir de la page en cours,
    *   du nb de r�sultat de la requete,
    */
    public function pagination()
    {
        $nbpages = ceil($this->count() / $this->nb_lignes);
        $page = $this->page;
        $nbliens = $this->nb_liens;
        $param = $this->paramsListe();

        $pagination = false;
        $page = empty($page) ? 1 : $page;       // au cas o�...
        if ($nbpages == 1 or empty($nbpages)) { return; }
        // Autres param�tres
        //$param = $param != '' ? '&amp;'.$param : '';
        $avant = $apres= floor($nbliens / 2);
        //echo "Page : $page - Nbpages : $Nbpages - param : $param<br>";
        //echo "first : $first - last : $last<br>";

        // Calcul premi�re et derni�re page � afficher
        if ($nbpages > $avant + $apres) {
            if ($page > $avant + 1) {
                if ($page + $apres > $nbpages) {
                    $last = $nbpages;
                    $first = $last - ($avant + $apres);
                } else {
                    $last = $page + $apres;
                    $first = $page - $avant;
                }
            }
            else {
                if ($page - $avant < 1) {
                    $first = 1;
                    $last = $first + ($avant + $apres);
                } else {
                    $last = $page + $apres;
                    $first = $page - $avant;
                }
            }
        }
        else {
            $first = 1;
            $last = $nbpages;
        }
        // Pr�c�dente et suivante
        $precedente = $page >1 ? $page - 1 : 1;
        $suivante = $page < $nbpages ? $page + 1 : $nbpages;
        if ($page > 1) {
            $pagination .= '<a href="?page=1'.$param.'" title="'._('Premi�re page').'">&laquo;</a>';
            $pagination .= '<a href="?page='.$precedente.$param.'" title="'._('Page pr�c�dente').'">&#8249;</a>';
        }
        $first = $first == 0 ? $page : $first;
        $last   = $last == 0 ? $page : $last;
        for ($i = $first; $i <= $last ; $i++) {
            $pagination .= $i == $page ? '<strong>'.$i.'</strong>' : '<a href="?page='.$i.$param.'">'.$i.'</a>';
        }
        if ($page < $nbpages) {
            $pagination .= '<a href="?page='.$suivante.$param.'" title="'._('Page suivante').'">&#8250;</a>';
            $pagination .= '<a href="?page='.$nbpages.$param.'" title="'._('Derni�re page').'">&raquo;</a>';
        }
        return '<p class="pagination">'.$pagination.'</p>';
    }

    /**
    * Retourne le label d'une liste
    * @param varchar $colonne
    * @param varchar $label
    * @return varchar label
    */
    public function labelTri($colonne, $label = '')
    {
        $label = empty($label) ? $colonne : $label;
        $ordre = $this->tri_ordre_default;
        $ordre_asc = _('croissant');
        $ordre_desc = _('d�croissant');

        if ($this->tri['colonne'] == $colonne) {
            $ordre = $this->tri['ordre'] == 'ASC' ? 'DESC' : 'ASC';
            $img = '<img src="'.URL.'administration/images/%s.png" alt="%s" />';
            $ordre_label = $ordre == 'ASC' ? $ordre_asc : $ordre_desc;
            $img = sprintf($img, strtolower($this->tri['ordre']), $ordre_label);
        }
        else {
            $img = '';
            $ordre_label = $ordre == 'ASC' ? $ordre_asc : $ordre_desc;
        }

        return '<a rel="nofollow" href="?tri='.$colonne.$this->paramsListe().'" title="Trier '.htmlXspecialchars($label).' par ordre '.$ordre_label.'">'
        .htmlXspecialchars($label).' '.$img.'
        </a>';
    }
}