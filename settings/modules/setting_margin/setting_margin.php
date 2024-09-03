<?php

namespace sv_core;

class setting_margin extends settings{
	private $parent				= false;

	public function __construct($parent=false){
		$this->parent			= $parent;
	}
	public function get_css_data(string $custom_property = '', string $prefix = '', string $suffix = ''): array{
		$property				= ((strlen($custom_property) > 0) ? $custom_property : 'margin');
		$properties				= array();

		if($this->get_parent()->get_data()) {
			$imploded		= [];
			foreach($this->get_parent()->get_data() as $breakpoint => $val) {
				$top = (isset($val['top']) && strlen($val['top']) > 0) ? $val['top'] : false;
				$right = (isset($val['right']) && strlen($val['right']) > 0) ? $val['right'] : false;
				$bottom = (isset($val['bottom']) && strlen($val['bottom']) > 0) ? $val['bottom'] : false;
				$left = (isset($val['left']) && strlen($val['left']) > 0) ? $val['left'] : false;

				if($top !== false || $right !== false || $bottom !== false || $left !== false) {
					$imploded[$breakpoint] = $top . ' ' . $right . ' ' . $bottom . ' ' . $left;
				}
			}
			if(empty($imploded)) {
				$properties[$property] = $this->prepare_css_property_responsive($imploded, $prefix, $suffix); // unnecessary , returns the same as line 56?
			}
		}

		return $properties;
	}
}