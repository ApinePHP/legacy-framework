<?php
/**
 * Request Router for Web apps
 * This script contains a routing helper to route the request toward controllers
 *
 * @license MIT
 * @copyright 2015 Tommy Teasdale
 */

/**
 * Web App Request Router
 * Route requests toward the best matching controller. This is part of the MVC architecture
 * 
 * @author Tommy Teasdale
 */
final class ApineWebRouter implements ApineRouterInterface {
	
	/**
	 * Find matching route in XML route configuration and return modified request string 
	 * 
	 * @return mixed
	 */
	private function xml_route () {
		
		$xml_routes = new Parser();
		$xml_routes->load_from_file('routes.xml');
		$request = (isset(Request::get()['request'])) ? Request::get()['request'] : '/index';
		$route_found = false;
		
		$routes = $xml_routes->getElementsByAttributeValue('method', Request::get_request_type());
		
		$str_routes = "";
		$found_route = null;
		
		foreach ($routes as $item) {
			
			if ($item->nodeType == XML_ELEMENT_NODE) {
				foreach ($item->childNodes as $attr) {
		        	if ($attr->nodeType == XML_ELEMENT_NODE){
		        		if ($attr->tagName == "request") {
		        			if ($item->getAttribute('method') == $_SERVER['REQUEST_METHOD']) {
		        				$match_route = $item->cloneNode(true);
		        				//print "{$match_route->getElementsByTagName('request')->item(0)->nodeValue}\n";
		        				
		        				$controller = $match_route->getElementsByTagName('controller')->item(0)->nodeValue;
		        				$action = $match_route->getElementsByTagName('action')->item(0)->nodeValue;
		        				
		        				$match = str_ireplace('/','\\/',$match_route->getElementsByTagName('request')->item(0)->nodeValue);
		        				//$match.="(\\/(.*))?";
		        				$match = '/^' . $match . '$/';
		        				$replace = "/$controller/$action";
		        				
		        				if ($match_route->getAttribute('args') == true) {
		        					$number_args = ($match_route->getAttribute('argsnum')!==null) ? $match_route->getAttribute('argsnum') : 1;
		        					
		        					for ($i = 1; $i <= $number_args; $i++) {
		        						$replace .= "/$" . $i;
		        					}
		        				}
		        				
		        				if(preg_match($match, $request)){
		        					$request = preg_replace($match, $replace, $request);
		        					$found_route = $item->cloneNode(true);
		        					
		        					//print "Found\n";
		        					break;
		        				}
		        			}
		        		}
		        	}
				}
		    }
		    
		    if ($found_route !== null) {
		    	break;
		    }
		}
		
		return $request;
		
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see ApineRouterInterface::route()
	 */
	public function route ($request) {
		
		$route_found = false;
		
		$vanilla_route_found = self::check_route($request);
		
		if (!$vanilla_route_found) {
			$xml_request = self::xml_route();
			
			if ($xml_request !== $request) {
				$route_found = true;
				$request = $xml_request;
			}
		}
		
		$args = explode("/",$request);
		array_shift($args);
		
		if (count($args) > 1) {
			$controller = $args[0];
			array_shift($args);
			$action = $args[0];
			array_shift($args);
		} else {
			$controller = $args[0];
			array_shift($args);
			$action = "index";
		}
		
		// Add post arguments to args array
		if (Request::get_request_type() != "GET") {
			$args = array_merge($args, Request::post());
		}
		
		if (!empty(Request::files())) {
			$args = array_merge($args, array("uploads" => Request::files()));
		}
		
		try {
			$maj_controller = ucfirst($controller) . 'Controller';
			
			if (class_exists($maj_controller) && method_exists($maj_controller, $action)) {
				$route = new ApineRoute($controller, $action, $args);
			}else if (file_exists('controllers/' . $controller . '_controller.php')) {
				require_once('controllers/' . $controller . '_controller.php');

				if (method_exists($maj_controller, $action)) {
					$route = new ApineRoute($controller, $action, $args);
				}
			}
			
			if (!isset($route)) {
				if ($route_found) {
					throw new ApineException("Reference Found but Action not Accessible for Route \"$controller\"", 410);
				} else {
					throw new ApineException("Route \"$controller\" not Found", 404);
				}
			}
			
			return $route;
			
		} catch (Exception $e) {
			throw new ApineException($e->getMessage(), $e->getCode(), $e);
		}
		
	}
	
	/**
	 * Verifies if the request string matches an existing controller
	 *  
	 * @param string $a_route
	 * @return boolean
	 */
	private function check_route ($a_route) {
		
		$args = explode("/",$a_route);
		array_shift($args);
		
		if (count($args) > 1) {
			$controller = $args[0];
			array_shift($args);
			$action = $args[0];
			array_shift($args);
		} else {
			$controller = $args[0];
			array_shift($args);
			$action = "index";
		}
		
		try {
			$maj_controller = ucfirst($controller) . 'Controller';
			if (class_exists($maj_controller) && method_exists($maj_controller, $action)) {
				return true;
			}else if (file_exists('controllers/' . $controller . '_controller.php')) {
				require_once('controllers/' . $controller . '_controller.php');
				
				if (method_exists($maj_controller, $action)) {
					//print "Found";
					return true;
				}
			}
			
			return false;
		} catch (Exception $e) {
			//print "Error";
			//self::execute('ErrorController', 'server');
			//return false;
			throw new ApineException($e->getMessage(), $e->getCode(), $e);
		}
		
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see ApineRouterInterface::execute()
	 */
	public function execute ($controller, $action, $args = null) {
		
		if (self::check_route("/$controller/$action")) {
			$maj_controller = ucfirst($controller) . 'Controller';
			$controller = new $maj_controller();
			$controller->$action($args);
		} else {
			throw new ApineException("Route \"$controller\" Not found", 404);
		}
		
	}
	
}