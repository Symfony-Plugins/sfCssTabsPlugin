<?php
/**
 * sfCssTabs
 * 
 * Plugin for the Framework Symfony that generates tabs dynamically depending 
 * on the module that this seeing itself, to the style Wordpress Admin 
 * (of 2 levels: main tabs & sub tabs), with valid CSS and XHTML.
 * 
 * LICENSE: This source file is subject to MIT license, that is available 
 * through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/mit-license.php
 * 
 * @author    pedro hernandez <phpleo@gmail.com> http://blog.phpleo.com
 * @copyright phpleo.Factory
 * @license   http://www.opensource.org/licenses/mit-license.php   MIT
 * @version   Release: 0.1.6 beta
 * @link      http://blog.phpleo.com/
 * @since     Class available since PHP 5.0.0
 */

class sfCssTabs extends sfWebRequest
{
	// {{{ properties
	
	/**
	* nombre del archivo principal a cargar si no
	* se especifica
	* 
	* @var    string
	* @access private
	*/
	private $nameMainFile = 'ctSite';
	
	/**
	* Obtained configuration of the variable $configTabs, 
	* that is in the file siteTabs.php
	* 
	* @var    array
	* @access private
	*/
	private $configTabs;
	
	/**
	* Name of the module that is assets at the moment
	* 
	* @var    string
	* @access private
	*/
	private $actualModule;
	
	/**
	* Name of the first action that is active at this moment. Example
	* - www.mydomain.com/module1/action1
	* 
	* @var    string
	* @access private
	*/
	private $actualAction = '';
	
	/**
	* Configuration of the main tabs
	* 
	* @var    array
	* @access private
	*/
	private $mainTabs = array();
	
	/**
	* configuration of the sub tabs
	* 
	* @var    array
	* @access private
	*/
	private $subTabs = array();
	
	/**
	* name of the parent tab to whom a sub tab belongs
	* 
	* @var    string
	* @access private
	*/
	private $parentTab;
	
	/**
	* Instance of the class via the singleton pattern
	* 
	* @var    object
	* @access private
	*/
	private static $instance;
	
	// }}}
	// {{{ constructor

	/**
	* It makes call to the method to identify the module and the action
	* 
	* @access private
	* @return void
	*/
	private function __construct()
	{
		$this->detectModuleAndAction();
	}
	
	// }}}
	// {{{ singleton()
	
	/**
	* Singleton pattern
	* 
	* @access public
	* @return object
	*/
	public static function singleton()
	{
		if( !isset(self::$instance) )
		{
			$class = __CLASS__;
			
			self::$instance = new $class;
		}
		
		return self::$instance;
	}
	
	// }}}
	// {{{ recognizeFile()
	
	/**
	* It gives format to the name of the file to load
	* 
	* @access private
	* @param  string $name name of the file YAML
	* @return string
	*/
	private function recognizeFile( $name )
	{
		if( empty($name) )
			$name = $this->nameMainFile . '.yml';
		else
			$name = 'ct' . ucfirst( $name ) . '.yml';
			
		$ymlFile1 = sfConfig::get('sf_csstabs_config_tabs') . $name;
		$ymlFile2 = sfConfig::get('sf_config_dir') . DIRECTORY_SEPARATOR . $name;
			
		if( file_exists($ymlFile1) )
			$name = $ymlFile1;
		elseif( file_exists($ymlFile2) )
			$name = $ymlFile2;
		
		return $name;
	}
	
	// }}}
	// {{{ loadAndAssignConfig()
	
	/**
	* It loads file YAML and it assigns the configuration to its respective variables
	* 
	* @access private
	* @param  string $name name of the file YAML
	* @return void
	*/
	private function loadAndAssignConfig( $name )
	{
		$yf = sfYaml::load( $this->recognizeFile($name) );
		$yf = $yf['cssTabs'];
	
		// recuperando la configuración principal
		$this->configTabs = $yf['configTabs'];
		
		// comprobando si existen pestañas
		if( !empty($yf['mainTabs'][0]['label']) )
			$this->mainTabs = $yf['mainTabs'];
		
		// sub tabs
		if( !empty($yf['subTabs'][0]['label']) )
			$this->setSubTabs( $yf['subTabs'] );
	}
	
	// }}}
	// {{{ detectModuleAndAction()
	
	/**
	* It detects the present module and the first action of this
	* 
	* @access private
	* @return void
	*/
	private function detectModuleAndAction()
	{
		$pathInfo = split( "/", $this->getPathInfo() );
		
		$this->actualModule = $pathInfo[1];
		
		if( isset($pathInfo[2]) )
			$this->actualAction = $pathInfo[2];
	}
	
	// }}}
	// {{{ render()

	/**
	* Print as much tabs as sub tabs one with UL & LI.
	* If it were defined in the configuration, are 
	* surrounded within a DIV
	* 
	* @access private
	* @param  string $name (optional) name of the file YAML
	* @return string
	*/
	public function render( $name='' )
	{
		$this->loadAndAssignConfig( $name );
		
		$stabs   = '';
		$subTabs = $this->getSubTabs();
		
		if( isset($subTabs[0]) )
			$stabs = $this->buildSubTabs();

		$tabs  = $this->buildMainTabs( $this->parentTab );
		
		$rt  = $tabs;
		$rt .= $stabs;
				
		if( !empty($this->configTabs['div']) )
			$rt = content_tag('div', $rt, $this->configTabs['div']);
		
		echo $rt;
	}
	
	// }}}
	// {{{ buildMainTabs()
	
	/**
	* Give back the main tabs
	* 
	* @access private
	* @param  string $parentTab name of the parent tab
	* @return string the main tabs with format
	*/
	private function buildMainTabs( $parentTab = '' )
	{
		return $this->buildTabs( $this->mainTabs, 'mt_', 1, $parentTab );
	}
	
	// }}}
	// {{{ buildSubTabs()
	
	/**
	* 1. Identifies parent of the present module
	* 2. Extracts the sub tabs that agree with he himself parent
	* 3. Gives back the sub tabs
	* 
	* @access private
	* @return string
	*/
	private function buildSubTabs()
	{
		$newSubTabs = array();
		$parentTab  = '';
		
		// I obtain parent of the present module
		foreach( $this->getSubTabs() as $stab )
			if( $stab['parentTab'] == $this->actualModule || $stab['module'] == $this->actualModule )
				$parentTab = $stab['parentTab'];
				
		$this->parentTab = $parentTab;
		
		// take those single that have he himself parent
		foreach( $this->getSubTabs() as $stab )
			if( $stab['parentTab'] == $parentTab )
				$newSubTabs[] = $stab;
		
		return $this->buildTabs( $newSubTabs, 'st_', 2 );
	}
	
	// }}}
	// {{{ buildTabs()
	
	/**
	* Build and it gives format to the tabs or are main or secondary (sub tabs)
	* It makes use of following helpers
	* 
	* 1. link_to     (UrlHelper.php)
	* 2. content_tag (TagHelper.php)
	* 
	* @access private
	* @param  array   $tabs       The tabs to process
	* @param  string  $prefix     It can be "pt_" (main tabs) or "st_" (sub tabs)
	* @param  integer $levelTabs  The level of te tab: (1) main tab, (2) sub tab
	* @param  string  $parentTabs The name of the parent tab to whom the secondary ones belong
	* @return string The tabs with format surrounded between UL, LI & A
	*/
	private function buildTabs( $tabs, $prefix, $levelTabs, $parentTab = '' )
	{
		$li = '';
		$internalUri = '';
		
		if( empty($parentTab) )
			$actualModule = $this->actualModule;
		else
			$actualModule = $parentTab;
	
		foreach( $tabs as $tab )
		{
			$options = "";
			
			if( !empty($this->actualAction) && $levelTabs == 2 )
			{
				if( $tab['module'] == $actualModule && $tab['action'] == $this->actualAction )
				{
					$tab['linkOptions'] += $this->configTabs[$prefix.'a'];
					$options             = $this->configTabs[$prefix.'li'];
				}
			}
			elseif( $tab['module'] == $actualModule && $levelTabs == 2 )
			{
				$tab['linkOptions'] += $this->configTabs[$prefix.'a'];
				$options             = $this->configTabs[$prefix.'li'];
			}
			elseif( $tab['module'] == $actualModule && $levelTabs == 1 )
			{
				$tab['linkOptions'] += $this->configTabs[$prefix.'a'];
				$options             = $this->configTabs[$prefix.'li'];
			}
			
			// formando el URI
			if( isset($tab['action']) )
				$internalUri = $tab['module']. '/' .$tab['action'];
			else
				$internalUri = $tab['module'];
			
			// formando el contenido de <a> y <li>
			$a   = link_to($tab['label'], $internalUri, $tab['linkOptions']);
			$li .= content_tag('li', $a, $options);
		}
		
		$ulLi = content_tag('ul', $li, $this->configTabs[$prefix.'ul']);
		
		return $ulLi;
	}
	
	private function setSubTabs( $tabs )
	{
		$this->subTabs = $tabs;
	}
	
	private function getSubTabs()
	{
		return $this->subTabs;
	}
	
	// }}}
	// {{{ destructor()
	
	/**
	* Destructor.
	* 
	* @access public
	* @return void
	*/
	public function __destruct()
	{
	}
}