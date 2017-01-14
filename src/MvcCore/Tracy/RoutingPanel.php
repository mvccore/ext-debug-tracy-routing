<?php

/**
 * MvcCore
 *
 * This source file is subject to the BSD 3 License
 * For the full copyright and license information, please view
 * the LICENSE.md file that are distributed with this source code.
 *
 * @copyright	Copyright (c) 2016 Tom Flídr (https://github.com/mvccore/mvccore)
 * @license		https://mvccore.github.io/docs/mvccore/3.0.0/LICENCE.md
 */

class MvcCoreExt_Tracy_RoutingPanel implements \Tracy\IBarPanel {
	/**
	 * Debug panel id
	 * @var string
	 */
	public static $Id = 'routing-panel';
	/**
	 * Prepared view data, only once,
	 * to render debug tab and debug panel content.
	 * @var stdClass
	 */
	protected static $viewData = NULL;
	/**
	 * Return unique panel id.
	 * @return string
	 */
	public function getId() {
		return self::$Id;
	}
	/**
	 * Render tab (panel header).
	 * Set up view data if necessary.
	 * @return string
	 */
	public function getTab() {
		ob_start();
		$view = self::setUpViewData();
		if ($view) require(__DIR__ . '/assets/Bar/routing.tab.phtml');
		return ob_get_clean();
	}
	/**
	 * Render panel (panel content).
	 * Set up view data if necessary.
	 * @return string
	 */
	public function getPanel() {
		ob_start();
		$view = self::setUpViewData();
		if ($view) require(__DIR__ . '/assets/Bar/routing.panel.phtml');
		return ob_get_clean();
	}
	/**
	 * Set up view data, if data are completed,
	 * return them directly.
	 * - complete basic MvcCore core objects to complere other view data
	 * - complete panel title
	 * - complete routes table items
	 * - set result data into static field
	 * @return object
	 */
	public static function setUpViewData () {
		if (static::$viewData) return static::$viewData;

		// complete basic MvcCore core objects to complere other view data
		$app = MvcCore::GetInstance();
		/** @var $request MvcCore_Request */
		$request = $app->GetRequest();
		/** @var $router MvcCore_Router */
		$router = $app->GetRouter();
		/** @var $routes MvcCore_Route[] */
		if (is_null($router)) return array(); // this is only by media site version switching
		$routes = $router->GetRoutes();
		/** @var $currentRoute MvcCore_Route */
		$currentRoute = $router->GetCurrentRoute();

		// complete panel title
		$panelTitle = 'no route';
		if (!is_null($currentRoute)) {
			$ctrlAndAction = $currentRoute->Controller . '::' . $currentRoute->Action;
			if ($ctrlAndAction != $currentRoute->Name) {
				$panelTitle = $currentRoute->Name . ' (' . $ctrlAndAction . ')';
			} else {
				$panelTitle = $ctrlAndAction;
			}
		}

		// complete routes table items
		$items = array();
		$matched = FALSE;
		foreach ($routes as $routeKey => & $route) {
			$items[] = static::completeItem($currentRoute, $route, $request);
			if ($currentRoute && $route->Name == $currentRoute->Name) $matched = TRUE;
		}
		if (!$matched) {
			if ($currentRoute instanceof MvcCore_Route) {
				$currentRoute->SetPattern('index.php?controller=...&action=...');
			} else {
				$currentRoute = new MvcCore_Route(array('name' => '')); // not found
			}
			$item = static::completeItem($currentRoute, $currentRoute, $request);
			$item->matched = 2;
			$items[] = $item;
		}

		// set result data into static field
		static::$viewData = (object) array(
			'panelTitle'		=> $panelTitle,
			'items'				=> $items,
			'requestMethod'		=> htmlSpecialChars($request->Method, ENT_IGNORE, 'UTF-8'),
			'requestBaseUrl'	=> htmlSpecialChars($request->BaseUrl, ENT_IGNORE, 'UTF-8'),
			'requestRequestPath'=> htmlSpecialChars($request->RequestPath, ENT_IGNORE, 'UTF-8'),
		);

		return static::$viewData;
	}
	/**
	 * Complete single route table row view data.
	 * In very special cases $currentRoute object shoud be null and
	 * also $route object shoud be null, those cases are mostly 
	 * some combinations with server errors (500) or not found errors (404).
	 * - first column
	 * - second column
	 * - third column
	 * - fourth column only if route is the same ass current route
	 * - complete result collection and returns it
	 * @param MvcCore_Route $currentRoute
	 * @param MvcCore_Route $route 
	 * @param MvcCore_Request $request 
	 * @return object
	 */
	protected static function completeItem (MvcCore_Route & $currentRoute = NULL, MvcCore_Route & $route = NULL, MvcCore_Request & $request = NULL) {
		// first column
		$matched = $currentRoute && $currentRoute->Name == $route->Name ? 1 : 0;

		// second column
		$routeClass = htmlSpecialChars(get_class($route), ENT_QUOTES, 'UTF-8');
		$routePattern = static::completeFormatedPatternOrReverseCharGroups($route->Pattern, array('(', ')'));
		$routeReverse = static::completeFormatedPatternOrReverseCharGroups($route->Reverse, array('{', '}'));

		// third column
		$routeCtrlActionName = $route->Controller . '::' . $route->Action;
		$routeCtrlActionLink = static::completeCtrlActionLink($route->Controller, $route->Action);
		$routeParams = static::completeParams($route->Params, FALSE);

		// fourth column only if route is the same ass current route
		$matchedCtrlActionName = '';
		$matchedCtrlActionLink = array();
		$matchedParams = array();
		if ($matched) {
			$reqParams = $request->Params;
			$ctrlPascalCase = MvcCore_Tool::GetPascalCaseFromDashed($reqParams['controller']);
			$actionPascalCase = MvcCore_Tool::GetPascalCaseFromDashed($reqParams['action']);
			$matchedCtrlActionName = $ctrlPascalCase . '::' . $actionPascalCase;
			$matchedCtrlActionLink = static::completeCtrlActionLink($ctrlPascalCase, $actionPascalCase);
			$matchedParams = static::completeParams($reqParams, TRUE);
		}

		// complete result collection and returns it
		return (object) array(
			'matched'				=> $matched,
			'routeClass'			=> $routeClass,
			'routePattern'			=> $routePattern,
			'routeReverse'			=> $routeReverse,
			'routeCtrlActionName'	=> $routeCtrlActionName,
			'routeCtrlActionLink'	=> $routeCtrlActionLink,
			'routeName'				=> $route->Name,
			'routeCustomName'		=> $routeCtrlActionName !== $route->Name,
			'routeParams'			=> $routeParams,
			'matchedCtrlActionName'	=> $matchedCtrlActionName,
			'matchedCtrlActionLink'	=> $matchedCtrlActionLink,
			'matchedParams'			=> $matchedParams,
		);
	}
	/**
	 * Complete third or fourth column params collection template string.
	 * @param array $params 
	 * @param bool  $skipCtrlActionRecord 
	 * @return array
	 */
	protected static function completeParams ($params = array(), $skipCtrlActionRecord = TRUE) {
		$result = array();
		foreach ($params as $key1 => $value1) {
			if ($skipCtrlActionRecord) if ($key1 == 'controller' || $key1 == 'action') continue;
			$key2 = htmlSpecialChars($key1, ENT_IGNORE, 'UTF-8');
			if (is_string($value1)) {
				$value2 = '<span class="tracy-dump-string">"'
					. htmlSpecialChars($value1, ENT_IGNORE, 'UTF-8')
					. '"</span><br />';
			} else {
				$value2 = \Tracy\Dumper::toHtml($value1, array(
					\Tracy\Dumper::COLLAPSE => TRUE,
					\Tracy\Dumper::LIVE => TRUE
				));
			}
			$result[$key2] = $value2;
		}
		return $result;
	}
	/**
	 * Add into route regular expression pattern or reverse ($route->Pattern
	 * or $route->Reverse) around all detected character groups special 
	 * html span elements to color them in template.
	 * @param string   $str      route pattern string or reverse string
	 * @param string[] $brackets array with specified opening bracket and closing bracket type
	 * @return string
	 */
	protected static function completeFormatedPatternOrReverseCharGroups ($str, $brackets) {
		$str = htmlSpecialChars($str, ENT_NOQUOTES, 'UTF-8');
		$matches = static::completeMatchingBracketsPositions($str, $brackets[0], $brackets[1]);
		if ($matches) {
			$pos = 0;
			$result = '';
			foreach ($matches as $key => & $match) {
				list($subStr, $begin, $end) = $match;
				$result .= mb_substr($str, $pos, $begin - $pos);
				$result .= '<span class="c'.($key % 6).'">';
				$result .= $subStr;
				$result .= '</span>';
				$pos = $end + 1;
			}
			$result .= mb_substr($str, $pos);
		} else {
			$result = $str;
		}
		return $result;
	}
	/**
	 * Complete collection with first level matching brackets,
	 * info about substrings between them and theur opening and closing
	 * positions to complete task with character group coloring in
	 * local method self::completeFormatedPatternOrReverseCharGroups().
	 * @param string $str	string to search brackets in
	 * @param string $begin	opening bracket char
	 * @param string $end	closing bracket char
	 * @return array[]
	 */
	protected static function completeMatchingBracketsPositions ($str, $begin, $end) {
		$result = array();
		preg_match_all('#([\\'.$begin.'\\'.$end.'])#', $str, $matches, PREG_OFFSET_CAPTURE);
		if ($matches[0]) {
			$matches = $matches[0];
			$level = 0;
			$groupBegin = -1;
			foreach ($matches as $item) {
				list($itemChar, $itemPos) = $item;
				$backSlashesCnt = 0;
				$backSlashPos = $itemPos - 1;
				while ($backSlashPos > -1 && true) {
					$prevChar = mb_substr($str, $backSlashPos, 1);
					if ($prevChar == '\\') {
						$backSlashesCnt += 1;
						$backSlashPos -= 1;
					} else {
						break;
					}
				}
				if (
					$backSlashesCnt === 0 || (
					($backSlashesCnt > 0 && $backSlashesCnt % 2 === 0)
				)) {
					if ($itemChar == $begin) {
						if ($level === 0) {
							$groupBegin = $itemPos;
						}
						$level += 1;
					} else {
						$level -= 1;
						if ($level === 0) {
							$result[] = array(
								mb_substr($str, $groupBegin, $itemPos - $groupBegin + 1), 
								$groupBegin, 
								$itemPos
							);
						}
					}
				}
			}
		}
		return $result;
	}
	/**
	 * Try to complete editor link by controller class name and it's action
	 * by PHP reflection object if controller class exist and it's possible by
	 * loaded controller class to create reflection object to complete link properties.
	 * Result is returned as array containing:
	 *  0 - editor link url
	 *  1 - link text
	 * @param string $ctrlName
	 * @param string $actionName 
	 * @return array
	 */
	protected static function completeCtrlActionLink ($ctrlName = '', $actionName = '') {
		$fullControllerClassName = '';
		if (substr($ctrlName, 0, 1) == '/') {
			$fullControllerClassName = substr($ctrlName, 1);
		} else {
			$fullControllerClassName = 'App_Controllers_' . $ctrlName;
		}
		$result = array('', $fullControllerClassName . '::' . $actionName . 'Action');
		try {
			$ctrlReflection = new ReflectionClass($fullControllerClassName);
			if ($ctrlReflection instanceof ReflectionClass) {
				$file = $ctrlReflection->getFileName();
				$actionReflection = $ctrlReflection->getMethod($actionName . 'Action');
				if ($actionReflection instanceof ReflectionMethod) {
					$line = $actionReflection->getStartLine();
					$result = array(
						\Tracy\Helpers::editorUri($file, $line),
						$fullControllerClassName . '::' . $actionName . 'Action'
					);
				}
			}
		} catch (Exception $e) {
		}
		return $result;
	}
}