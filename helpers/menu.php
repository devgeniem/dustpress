<?php
$this->dust->helpers['menu'] = function ( \Dust\Evaluate\Chunk $chunk, \Dust\Evaluate\Context $ctx, \Dust\Evaluate\Bodies $bodies, \Dust\Evaluate\Parameters $params, $dummy = false ) {	
	if ( $bodies->dummy !== true ) {
		global $dustpress;
		
		if ( ! isset( $params->menu_name ) ) {
			return $chunk->write("DustPress menu helper error: No menu specified.");
		}
		else {
			$menu_name = $params->menu_name;
		}

		if ( isset( $params->parent ) ) {
			$parent = $params->parent;
		}
		else {
			$parent = 0;
		}

		if ( isset( $params->override ) ) {
			$override = $params->override;
		}
		else {
			$override = null;
		}

		if ( isset( $params->ul_classes ) ) {
			$ul_classes = $params->ul_classes;
		}
		else {
			$ul_classes = "";
		}

		if ( isset( $params->ul_id ) ) {
			$ul_id = $params->ul_id;
		}
		else {
			$ul_id = "";
		}

		if ( isset( $params->show_submenu ) ) {
			$show_submenu = $params->show_submenu;
		}
		else {
			$show_submenu = true;
		}

		$dp = new DustPressHelper();

		$menu = new stdClass();

		$menu->items = $dp->get_menu_as_items($menu_name, $parent, $override);
		$menu->ul_classes = $ul_classes;
		$menu->ul_id = $ul_id;
		$menu->show_submenu = $show_submenu;

		// add data into debugger
		$dustpress->set_debugger_data( 'Menu', $menu );

		$output = $dustpress->render( [
			"partial" => "menu",
			"data" => $menu,
			"type" => "html",
			"echo" => false
		]);

		return $chunk->write( $output );
	}
};