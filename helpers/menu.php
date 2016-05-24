<?php
$this->dust->helpers['menu'] = function ( \Dust\Evaluate\Chunk $chunk, \Dust\Evaluate\Context $ctx, \Dust\Evaluate\Bodies $bodies, \Dust\Evaluate\Parameters $params, $dummy = false ) {
	if ( $bodies->dummy !== true ) {

		if ( ! isset( $params->menu_name ) && ! isset( $params->menu_id ) ) {
			return $chunk->write("DustPress menu helper error: No menu specified.");
		}
		else if ( isset( $params->menu_id ) ) {
			$menu_id = $params->menu_id;
			$id_given = true;
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

		$menu = new stdClass();

		if ( $menu_name ) {
			$menu->items = DustPressHelper::get_menu_as_items( $menu_name, $parent, $override );
		}
		else {
			$menu->items = DustPressHelper::get_menu_as_items( $menu_id, $parent, $override, true );
		}

		$menu->ul_classes = $ul_classes;
		$menu->ul_id = $ul_id;
		$menu->show_submenu = $show_submenu;

		// add data into debugger
		dustpress()->set_debugger_data( 'Menu', $menu );

		$output = dustpress()->render( [
			"partial" => "menu",
			"data" => $menu,
			"type" => "html",
			"echo" => false
		]);

		return $chunk->write( $output );
	}
};