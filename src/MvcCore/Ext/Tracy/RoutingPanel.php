<?php

/**
 * MvcCore
 *
 * This source file is subject to the BSD 3 License
 * For the full copyright and license information, please view
 * the LICENSE.md file that are distributed with this source code.
 *
 * @copyright	Copyright (c) 2016 Tom Flídr (https://github.com/mvccore/mvccore)
 * @license		https://mvccore.github.io/docs/mvccore/4.0.0/LICENCE.md
 */

namespace MvcCore\Ext\Debug\Tracy;

class RoutingPanel implements \Tracy\IBarPanel
{
	/**
	 * MvcCore Extension - Debug - Tracy Panel - Routing - version:
	 * Comparation by PHP function version_compare();
	 * @see http://php.net/manual/en/function.version-compare.php
	 */
	const VERSION = '5.0.0-alpha';

	/**
	 * Reference to main application instance.
	 * @var \MvcCore\Application|\MvcCore\Interfaces\IApplication
	 */
	protected $app = NULL;

	/**
	 * Reference to current application request instance.
	 * @var \MvcCore\Request|\MvcCore\Interfaces\IRequest
	 */
	protected $request = NULL;

	/**
	 * Reference to current application router instance.
	 * @var \MvcCore\Router|\MvcCore\Interfaces\IRouter
	 */
	protected $router = NULL;

	/**
	 * Reference to all initialized application routes in router.
	 * @var \MvcCore\Route[]|\MvcCore\Interfaces\IRoute[]
	 */
	protected $routes = array();

	/**
	 * Reference to route matched by current request.
	 * @var \MvcCore\Route|\MvcCore\Interfaces\IRoute
	 */
	protected $currentRoute = NULL;

	/**
	 * Prepared view data, only once,
	 * to render debug tab and debug panel content.
	 * @var \stdClass|NULL
	 */
	protected $view = NULL;

	/**
	 * Return unique panel id.
	 * @return string
	 */
	public function getId() {
		return 'routing-panel';
	}
	/**
	 * Render tab (panel header).
	 * Set up view data if necessary.
	 * @return string
	 */
	public function getTab() {
		ob_start();
		$view = $this->getViewData();
		if ($view) include(__DIR__ . '/assets/Bar/routing.tab.phtml');
		return ob_get_clean();
	}
	/**
	 * Render panel (panel content).
	 * Set up view data if necessary.
	 * @return string
	 */
	public function getPanel() {
		ob_start();
		$view = $this->getViewData();
		if ($view) include(__DIR__ . '/assets/Bar/routing.panel.phtml');
		return ob_get_clean();
	}
	/**
	 * Set up view data, if data are completed,
	 * return them directly.
	 * - complete basic \MvcCore core objects to complere other view data
	 * - complete panel title
	 * - complete routes table items
	 * - set result data into static field
	 * @return object
	 */
	public function getViewData () {
		if ($this->view !== NULL) return $this->view;

		// complete basic \MvcCore core objects to complere other view data
		$this->initMainApplicationProperties();
		if ($this->router === NULL) return (object) array(); // this could be only by media site version switching

		// complete panel title
		$panelTitle = 'no route';
		if (!$this->currentRoute !== NULL) {
			$ctrlAndAction = $this->currentRoute->GetControllerAction();
			if ($ctrlAndAction != $this->currentRoute->GetName()) {
				$panelTitle = $this->currentRoute->GetName() . ' (' . $ctrlAndAction . ')';
			} else {
				$panelTitle = $ctrlAndAction;
			}
		}

		// complete routes table items
		$items = array();
		$matched = FALSE;
		/** @var $route \MvcCore\Interfaces\IRoute */
		foreach ($this->routes as & $route) {
			$items[] = $this->completeItem($route);
			if ($this->currentRoute && $route->GetName() == $this->currentRoute->GetName()) $matched = TRUE;
		}
		if (!$matched) {
			if ($this->currentRoute instanceof \MvcCore\Route) {
				$this->currentRoute->SetPattern('index.php?controller=...&action=...');
			} else {
				$this->currentRoute = new \MvcCore\Route(array('name' => '')); // not found
			}
			$item = $this->completeItem($this->currentRoute);
			$item->matched = 2;
			$items[] = & $item;
		}

		// set result data into static field
		$this->view = (object) array(
			'panelTitle'		=> $panelTitle,
			'items'				=> $items,
			'requestMethod'		=> htmlSpecialChars($this->request->GetMethod(), ENT_IGNORE, 'UTF-8'),
			'requestBaseUrl'	=> htmlSpecialChars($this->request->GetBaseUrl(), ENT_IGNORE, 'UTF-8'),
			'requestRequestPath'=> htmlSpecialChars($this->request->GetRequestPath(), ENT_IGNORE, 'UTF-8'),
		);

		return $this->view;
	}

	/**
	 * Initialize main application properties into current `$this`
	 * context: app, request, router, routes and current route.
	 * @return void
	 */
	protected function initMainApplicationProperties () {
		$this->app = & \MvcCore\Application::GetInstance();
		$this->request = & $this->app->GetRequest();
		$this->router = & $this->app->GetRouter();
		$this->routes = & $this->router->GetRoutes();
		$this->currentRoute = $this->router->GetCurrentRoute();
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
	 * @param \MvcCore\Route $currentRoute
	 * @param \MvcCore\Route $route
	 * @param \MvcCore\Request $request
	 * @return object
	 */
	protected function completeItem (\MvcCore\Interfaces\IRoute & $route = NULL) {
		$route->InitAll();

		// first column
		$matched = $this->currentRoute && $this->currentRoute->GetName() == $route->GetName() ? 1 : 0;

		// second column
		$app = \MvcCore\Application::GetInstance();
		$routeClass = htmlSpecialChars(get_class($route), ENT_QUOTES, 'UTF-8');
		$requestLang = $this->request->GetLang();
		$router = $app->GetRouter();
		$defaultLang = method_exists($router, 'GetDefaultLang') ? $router->GetDefaultLang() : '';
		$routePattern = $this->getRouteLocalizedRecord($route->GetMatch(), $requestLang, $defaultLang);
		$routeReverse = $this->getRouteLocalizedRecord($route->GetReverse(), $requestLang, $defaultLang);
		$routePattern = $this->completeFormatedPatternOrReverseCharGroups($routePattern, array('(', ')'));
		$routeReverse = $this->completeFormatedPatternOrReverseCharGroups($routeReverse, array('{', '}'));

		// third column
		$routeCtrlActionName = $route->GetControllerAction();
		$routeCtrlActionLink = $this->completeCtrlActionLink($route->GetController(), $route->GetAction());
		$routeParams = $this->completeParams($route, $route->GetDefaults(), FALSE);

		// fourth column only if route is the same ass current route
		$matchedCtrlActionName = '';
		$matchedCtrlActionLink = array();
		$matchedParams = array();
		if ($matched) {
			$reqParams = $this->request->GetParams();
			$ctrlPascalCase = \MvcCore\Tool::GetPascalCaseFromDashed($reqParams['controller']);
			$actionPascalCase = \MvcCore\Tool::GetPascalCaseFromDashed($reqParams['action']);
			$ctrlPascalCase = str_replace('/', '\\', $ctrlPascalCase);
			$matchedCtrlActionName = $ctrlPascalCase . ':' . $actionPascalCase;
			$matchedCtrlActionLink = $this->completeCtrlActionLink($ctrlPascalCase, $actionPascalCase);
			$matchedParams = $this->completeParams($route, $reqParams, TRUE);
		}

		// complete result collection and returns it
		return (object) array(
			'matched'				=> $matched,
			'routeClass'			=> $routeClass,
			'routePattern'			=> $routePattern,
			'routeReverse'			=> $routeReverse,
			'routeCtrlActionName'	=> $routeCtrlActionName,
			'routeCtrlActionLink'	=> $routeCtrlActionLink,
			'routeName'				=> $route->GetName(),
			'routeCustomName'		=> $routeCtrlActionName !== $route->GetName(),
			'routeParams'			=> $routeParams,
			'routeMethod'			=> $route->GetMethod(),
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
	protected function completeParams (\MvcCore\Route & $route, $params = array(), $skipCtrlActionRecord = TRUE) {
		$result = array();
		if (gettype($route->GetPattern()) == 'array') {
			$requestLang = \MvcCore\Application::GetInstance()->GetRequest()->GetLang();
			if ($requestLang !== NULL) {
				$result['lang'] = $value2 = '<span class="tracy-dump-string">"' . $requestLang . '"</span><br />';
			}
		}
		if ($params === NULL) return $result;
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
	 * Add into route regular expression pattern or reverse ($route->GetPattern()
	 * or $route->GetReverse()) around all detected character groups special
	 * html span elements to color them in template.
	 * @param string   $str      route pattern string or reverse string
	 * @param string[] $brackets array with specified opening bracket and closing bracket type
	 * @return string
	 */
	protected function completeFormatedPatternOrReverseCharGroups ($str, $brackets) {
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
	 * local method $this->completeFormatedPatternOrReverseCharGroups().
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
	protected function completeCtrlActionLink ($ctrlName = '', $actionName = '') {
		$fullControllerClassName = '';
		if (substr($ctrlName, 0, 1) == '\\') {
			$fullControllerClassName = substr($ctrlName, 1);
		} else {
			$fullControllerClassName = '\\App\\Controllers\\' . $ctrlName;
		}
		$result = array('', $fullControllerClassName . ':' . $actionName . 'Action');
		try {
			$ctrlReflection = new \ReflectionClass($fullControllerClassName);
			if ($ctrlReflection instanceof \ReflectionClass) {
				$file = $ctrlReflection->getFileName();
				$actionReflection = $ctrlReflection->getMethod($actionName . 'Action');
				if ($actionReflection instanceof \ReflectionMethod) {
					$line = $actionReflection->getStartLine();
					$result = array(
						\Tracy\Helpers::editorUri($file, $line),
						$fullControllerClassName . ':' . $actionName . 'Action'
					);
				}
			}
		} catch (\Exception $e) {
		}
		return $result;
	}
	/**
	 * Get route non-localized or localized record - 'Pattern' and 'Reverse'
	 * @param \MvcCore\Route $route
	 * @param string|array $routeRecord
	 * @param string $lang
	 * @param string $defaultLang
	 * @return string
	 */
	protected function getRouteLocalizedRecord ($routeRecord = NULL, $lang = '', $defaultLang = '') {
		if (gettype($routeRecord) == 'array') {
			if (isset($routeRecord[$lang])) {
				return $routeRecord[$lang];
			} else if (isset($routeRecord[$defaultLang])) {
				return $routeRecord[$defaultLang];
			}
			return reset($routeRecord);
		}
		return $routeRecord;
	}
}
