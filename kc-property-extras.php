<?php

/**
 * Plugin Name: KinoCommerce Property Extras
 * Description: Adds additional extras to properties and handles saving and payment of these.
 * Author: Kino Creative
 * Author URI: http://www.kinocreative.co.uk
 * Version: 1.0.0
 */


function kc_add_extra_checkout_fields( $checkout ) {

	?>
	<?php

	$additionalExtras = get_additional_extras();
	$freeExtras       = array();
	$paidExtras       = array();

	if ( $additionalExtras ) {
		?>
		<div class="extras-selection">

			<?php
			foreach ( $additionalExtras as $additionalExtra ) {

				$additionalExtraID = $additionalExtra->ID;

				$types = get_the_terms( $additionalExtraID, 'price' );

				foreach ( $types as $priceTaxonomy ) {

					if ( ! get_field( 'hidden_extra', $additionalExtraID ) ) {
						if ( 'Free' == $priceTaxonomy->name ) {
							$freeExtras[] = $additionalExtra;
						} elseif ( 'Paid' == $priceTaxonomy->name ) {
							$paidExtras[] = $additionalExtra;
						}
					}
				}
			}


			if ( count( $freeExtras ) ) :
				?>
				<p>The following extras are available at no additional cost. Please select any required.</p>

				<ul class="clearfix">
					<?php

					foreach ( $freeExtras as $freeExtra ) {

						$freeExtraID = $freeExtra->ID;

						kc_field_generator( get_field( 'extra_type', $freeExtraID ), $checkout, $freeExtraID );

					}

					?>
				</ul>
			<?php endif;

			if ( count( $paidExtras ) ) :
				?>
				<p>The following extras are additional extras and the cost will be added to your total.</p>

				<ul class="clearfix paid-extras">
					<?php

					foreach ( $paidExtras as $paidExtra ) {

						$paidExtraID = $paidExtra->ID;

						kc_field_generator( get_field( 'extra_type', $paidExtraID ), $checkout, $paidExtraID );

					}

					?>
				</ul>
			<?php endif; ?>
		</div>

	<?php
	}
//return $fields;
}

function get_additional_extras() {
	global $woocommerce;

	$additionalExtras = null;

	foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $cart_item ) {

		$product = $woocommerce->cart->get_cart_item( $cart_item_key );

		$productID = $product['product_id'];

		$additionalExtras = get_field( 'available_extras', $productID );

	}

	return $additionalExtras;
}

function kc_field_generator( $newField, $checkout, $addionalExtraID ) {

	$additionalExtraMeta = kc_label_to_meta( get_the_title( $addionalExtraID ) );

	$title = get_the_title( $addionalExtraID );

	if ( get_field( 'price', $addionalExtraID ) ) {
		$title .= ' (+£' . get_field( 'price', $addionalExtraID ) . ')';
	}
	?>
	<li>
		<?php
		if ( 'Checkbox' == $newField ) {

			woocommerce_form_field( $additionalExtraMeta, array(
				'type'     => 'checkbox',
				'label'    => $title,
				'required' => false,
				'clear'    => false,
			), $checkout->get_value( $additionalExtraMeta ) );
		}
		?>
	</li>
<?php
}

// Hook in
add_action( 'woocommerce_checkout_before_customer_details', 'kc_add_extra_checkout_fields' );


/**
 * Update the order meta with field value
 **/
add_action( 'woocommerce_checkout_update_order_meta', 'kc_custom_checkout_field_update_order_meta' );

function kc_custom_checkout_field_update_order_meta( $order_id ) {
	$additionalExtras = get_additional_extras();

	foreach ( $additionalExtras as $additionalExtra ) {
		$additionalExtraID   = $additionalExtra->ID;
		$additionalExtraMeta = kc_label_to_meta( get_the_title( $additionalExtraID ) );

		if ( filter_input( INPUT_POST, $additionalExtraMeta ) ) {
			update_post_meta( $order_id, $additionalExtraMeta, esc_attr( filter_input( INPUT_POST, $additionalExtraMeta ) ) );
		} else {
			if ( 'Checkbox' == get_field( 'extra_type', $additionalExtraID ) && get_field( 'default_value', $additionalExtraID ) ) {
				//Check if owner booking
				if(!get_post_meta($order_id, 'owner_booking', true)) {
					update_post_meta( $order_id, $additionalExtraMeta, 1 );
				} else {
					update_post_meta( $order_id, $additionalExtraMeta, 0 );
				}
			} elseif ( 'Checkbox' == get_field( 'extra_type', $additionalExtraID ) ) {
				update_post_meta( $order_id, $additionalExtraMeta, 0 );
			}
		}
	}
	//die();

}


// Hook in
add_action( 'woocommerce_checkout_before_customer_details', 'kc_add_find_us_field' );

function kc_add_find_us_field() {


	if ( have_rows( 'how_did_you_find_us', 'option' ) ) : ?>
		<label for="find-us" class="top-label">How did you find us?</label>
		<select name="find-us">


			<option value="">Please Select</option>
			<?php while ( have_rows( 'how_did_you_find_us', 'option' ) ) :
				the_row();
				?>
				<option><?php the_sub_field( 'option' ); ?></option>
			<?php
			endwhile; ?>
		</select>
	<?php
	endif;
}


add_action('woocommerce_after_checkout_validation', 'kc_require_form_fields');

function kc_require_form_fields($posted) {


	if(!$_POST['find-us'] || $_POST['find-us'] == "") {

		wc_add_notice( __( 'Please let us know how you found us.', 'woocommerce' ), 'error' );

	}

}



/**
 * Update the user meta with field value
 **/
add_action( 'woocommerce_checkout_update_user_meta', 'kc_update_user_meta', 10, 2 );

function kc_update_user_meta( $customer_id, $postdata ) {

	
	if(isset( $_POST['find-us']) &&  $_POST['find-us'] !== '') {
		update_user_meta( $customer_id, 'kc_how_did_you_find_us', $_POST['find-us'] );
	}
	update_user_meta( $customer_id, 'billing_mobile', $postdata['billing_mobile'] );


}

function kc_find_us_callback($post) {

	$order = wc_get_order( $post->ID );

	$user_id = $order->get_user_id();

	echo get_user_meta($user_id, 'kc_how_did_you_find_us', true);
}

function kc_meta_to_label( $meta ) {
	$meta = str_replace( 'kc_extra_', '', $meta );

	return ucwords( str_replace( '_', ' ', $meta ) );
}

function kc_label_to_meta( $label ) {
	$label = strtolower( str_replace( ' ', '_', $label ) );

	return 'kc_extra_' . $label;
}


add_action( 'woocommerce_cart_calculate_fees', 'kc_calculate_extras' );

function kc_calculate_extras( $cart ) {

	if ( ! defined( 'DOING_AJAX' ) ) {
		return $cart;
	}

	//Woocommerce sends post data in 2 different ways depending on what we're doing on
	//the checkout page. First try and handle if we're just updating the total. Otherwise
	//Handle when we're passing to the payment processor.
	if ( isset( $_POST['post_data'] ) ) {
		$postdata = filter_input( INPUT_POST, 'post_data' );
		parse_str( $postdata, $postdata );
	} else {
		$postdata = $_POST;
	}


	if ( ! is_array( $postdata ) ) {
		return $cart;
	}

	if ( get_additional_extras() ) {

		foreach ( get_additional_extras() as $availableExtra ) {
			foreach ( $postdata as $extra => $selected ) {

				$availableExtraID   = $availableExtra->ID;
				$availableExtraName = kc_label_to_meta( get_the_title( $availableExtraID ) );

				if ( $extra == $availableExtraName ) {

					$price = get_field( 'price', $availableExtraID );

					$cart->add_fee( __( get_the_title( $availableExtraID ), 'woocommerce' ), $price );
					//$cart->fee_total = $cart->fee_total + $price;
				}
			}
		}
	}

	//var_dump($cart);

	return $cart;
}


/*
  * Add custom meta box to woocommerce orders
  */

function kcpe_extras_meta() {
	add_meta_box( 'kcpe_meta', __( 'Booking Info', 'kcpe-info' ), 'kcpe_meta_callback', 'shop_order' );
	add_meta_box( 'kc_find_us_meta', 'How Did You Find Us?', 'kc_find_us_callback', 'shop_order', 'side' );
}

add_action( 'add_meta_boxes', 'kcpe_extras_meta' );

function kcpe_meta_callback( $post ) {
	wp_nonce_field( basename( __FILE__ ), 'kcpb_nonce' );
	$kcpe_stored_meta = get_post_meta( $post->ID );


	$order = new WC_Order( $post->ID );

	$items = $order->get_items();

	//var_dump( $kcpe_stored_meta );

	foreach ( $items as $item ) {

		$extras = get_field( 'available_extras', $item['product_id'] );

	}

	?>
	<table class="wp-list-table widefat fixed">
	<thead>
	<tr>
		<td>Name</td>
		<td>Price</td>
		<td>Active</td>
		<td>Hidden</td>
	</tr>
	</thead>
	<?php
	$i = 1;

	$defaultExtras = array();

	foreach ( $extras as $extra ) {

		$extraMeta = kc_label_to_meta($extra->post_title);

		$defaultExtras[] = $extraMeta;

		if(unserialize($kcpe_stored_meta[ $extraMeta ][0])) {
			$unserialized = unserialize($kcpe_stored_meta[ $extraMeta ][0]);
			kcpe_process_extra_table( $extra, $unserialized['active'], $i );
		} else {
			kcpe_process_extra_table( $extra, $kcpe_stored_meta[ $extraMeta ][0], $i );

		}

		$i++;
	}

	foreach ($kcpe_stored_meta as $extra => $value) {
		if(strpos($extra, 'kc_extra_') !== false && !in_array($extra, $defaultExtras)) {

			if(!is_serialized($value)) {
				$simple_extra = true;
			} else {
				$simple_extra = false;
			}
			kcpe_process_extra_table($extra, $value[0],$i, true, $simple_extra);

			$i++;

		}
	}


	if ( $order->get_status() === "partially-paid" || $order->get_status() === "pending" ) :
		?>

		<tr class="<?php echo( $i % 2 == 0 ? '' : 'alt' ); ?>">
			<td>
				<input type="text" placeholder="Extra Name" name="extra[0][name]"/>
			</td>
			<td>
				<input type="number" name="extra[0][price]" placeholder="Price"/>
			</td>
			<td>
				<input name="extra[0][active]" type="checkbox" />
			</td>
			<td>
				<input name="extra[0][hidden]" type="checkbox"/>
			</td>
		</tr>
		<tr>
			<td>
				<button class="add-another-extra">
					Add Extra
				</button>
			</td>
		</tr>
		</table>
	<?php

	endif;

}


function kcpe_process_extra_table($extra, $value = null, $i, $orderMeta = false, $simple_meta = false) {


	if($simple_meta) {
		$row['name']     = kc_meta_to_label($extra);
		$row['metaname'] = $extra;

		$row['active'] = ( isset( $value ) && $value == 1 ? 'checked' : '' );

		$row['hidden'] = '';
	} elseif($orderMeta) {

		$extraArray = unserialize($value);

		if($extraArray) {
			$row['name']     = $extraArray['name'];
			$row['price']    = $extraArray['price'];


			if($extraArray['active']) {
				$row['active'] = 'checked';
			}

			if($extraArray['hidden']) {

				$row['hidden'] = 'Yes';
			}
		} else {
			$row['name']     = kc_meta_to_label($extra);

		}
		$row['metaname'] = $extra;

	} else {


		$row['name']     = $extra->post_title;
		$row['metaname'] = kc_label_to_meta( $extra->post_title );

		$row['active'] = ( isset( $value ) && $value == 1 ? 'checked' : '' );

		$row['hidden'] = '';

	}


	if( get_field('hidden_extra', $extra->id) ) {
		$row['hidden'] = 'Yes';
	}


	//Output the row
	?>
	<tr class="<?php echo( $i % 2 == 0 ? '' : 'alt' ); ?>">
		<td>
			<?php echo $row['name']; ?>
		</td>
		<td>

			<?php if($orderMeta) : ?>

				<?php echo $row['price']; ?>
			<!--<input type="number" placeholder="<?php echo $row['price']; ?>" value="<?php echo $row['price']; ?>" name="<?php echo $row['metaname'] . '_price'; ?>" />-->

			<?php endif; ?>

		</td>
		<td>
			<input name="<?php echo $row['metaname']; ?>" type="hidden" value="0" />
			<input name="<?php echo $row['metaname']; ?>" type="checkbox" <?php echo $row['active']; ?> />
		</td>
		<td>

			<?php if($orderMeta) : ?>

				<?php echo $row['hidden']; ?>

				<!--<input name="<?php echo $row['metaname'] . '_hidden'; ?>" type="checkbox" <?php echo $row['hidden']; ?> />-->

			<?php endif; ?>
		</td>
	</tr>
	<?php
}

/** Save metabox */
function kcpe_extras_save( $post_id ) {

	//Remove action to stop duplicate entries.
	remove_action('save_post', 'kcpe_extras_save');

	if ( get_post_type( $post_id ) !== 'shop_order' ) {
		return;
	}

	//Check save status
	$is_autosave    = wp_is_post_autosave( $post_id );
	$is_revision    = wp_is_post_revision( $post_id );
	$is_valid_nonce = ( isset( $_POST['kcpe_nonce'] ) && wp_verify_nonce( $_POST['kcpe_nonce'] ) );

	//Exit script depending on save status
	if ( $is_autosave || $is_revision || $is_valid_nonce ) {
		return;
	}

	$order = new WC_Order( $post_id );
	$fees = $order->get_fees();

	foreach ( $_POST as $metaName => $metaValue ) {

		$existing = get_post_meta($post_id, $metaName, true);


		if ( strpos( $metaName, 'kc_' ) === 0 && (strpos($metaName, '_price') == false && strpos($metaName, '_hidden') == false)){
			//If active then add fee
			if ( $metaValue == "on" ) {
				$metaValue = 1;

				//Handle reactivating of fees if previously disabled
				//Check to make sure we aren't adding it again
				if(is_array($existing) && $existing['active'] == "0") {

					$fee         = new stdClass();
					$fee->tax    = 0;
					$fee->amount = (float)$existing['price'];
					$fee->name   = $existing['name'];


					if($existing['hidden']) {
						$fee->name   = 'Extra';
					}

					$order->add_fee( $fee );

				} elseif($existing == "0") {

					$fee         = new stdClass();
					$fee->tax    = 0;
					$extra = get_page_by_title(kc_meta_to_label($metaName), 'OBJECT', 'post');
					$fee->amount = (float)get_field('price', $extra->ID);
					$fee->name   = kc_meta_to_label($metaName);

					$order->add_fee( $fee );

				}


			} elseif( $metaValue === "0" ) { //Otherwise delete the fee
				$metaValue = 0;


				foreach($fees as $feeID => $feeData) {

					if(strpos($feeData["name"], kc_meta_to_label($metaName)) !== false) {
						wc_delete_order_item( absint( $feeID ) );
					}

				}

			}



			if(is_array($existing)) {

				$existing['active'] = $metaValue;

				update_post_meta( $post_id, $metaName, $existing );

			} else {
				update_post_meta( $post_id, $metaName, $metaValue );

			}
		}
	}

	//Handle adding fee
	//Editing Fee
	//Removing fee

	//Handle adding extras to the order
	if ( isset( $_POST['extra'] ) && $_POST['extra'][0]['name'] !== '' ) {

		foreach ( $_POST['extra'] as $newExtra ) {

			if ( $newExtra['name'] !== '' && $newExtra['price'] !== '' ) {

				$extraArray = array('name' => $newExtra['name'], 'price' => $newExtra['price'], 'active' => 1, 'hidden' => 0);

				if($newExtra['hidden']) {
					$extraArray['hidden'] = $newExtra['hidden'];
				}

				update_post_meta( $post_id, kc_label_to_meta( $newExtra['name'] ), $extraArray );

				$fee         = new stdClass();
				$fee->tax    = 0;
				$fee->amount = (float)$extraArray['price'];
				$fee->name   = $extraArray['name'];


				if($extraArray['hidden']) {
					$fee->name   = 'Extra';
				}

				$order->add_fee( $fee );

			}

		}

		$total = $order->calculate_totals();

		$depositPaid = (float)get_post_meta(  $order->id, '_wc_deposits_paid', true );
		$remaining = $total - $depositPaid;
		update_post_meta( $order->id, '_wc_deposits_remaining', $remaining );

		if($remaining > 0) {
			update_post_meta( $order->id, '_wc_deposits_remaining_paid', "no" );
		}

	}

	add_action('save_post', 'kcpe_extras_save');

}

add_action( 'save_post', 'kcpe_extras_save' );

function get_extras_by_order( $orderId ) {
	$additionalExtras = null;

	$order = new WC_Order( $orderId );

	foreach ( $order->get_items() as $cart_item_key => $cart_item ) {

		$productID = $cart_item['product_id'];

		$extras = get_field( 'available_extras', $productID );

		if ( $extras ) {

			foreach ( $extras as $extra ) {

				$metaName = kc_label_to_meta( $extra->post_title );

				$additionalExtras[ $metaName ] = $extra->post_title;

				if ( get_field( 'price', $extra->ID ) ) {
					$additionalExtras[ $metaName ] = $additionalExtras[ $metaName ] . ' (+£' . get_field( 'price', $extra->ID ) . ')';
				}

			}

		}

	}

	return $additionalExtras;
}


/*
 *
 * Handles the addition of extras from the frontend of the site
 *
 */
function kc_handle_extras_modifcation( $extras, $orderId ) {

	$order        = new WC_Order( $orderId );
	$existingFees = $order->get_fees();

	$newExtras    = array();
	$removeExtras = array();

	if($order->get_status() == "completed" || $order->get_status() == "processing") {
		$remaining = 0;
	} elseif ($order->get_status() == "partially-paid") {
		$remaining = (float) get_post_meta( $orderId, '_wc_deposits_remaining', true );
	} elseif ($order->get_status() == "pending") {
		$remaining = $order->get_total();
	}
	foreach ( $extras as $extra ) {

		$extraExists = false;

		foreach ( $existingFees as $fee ) {

			if ( strtolower($fee['name']) == strtolower(kc_meta_to_label( $extra )) ) {
				$extraExists = true;
			}
		}

		if ( ! $extraExists ) {
			$newExtras[] = $extra;
		}

	}


	//Add new fees/meta

	foreach ( $newExtras as $extra ) {
		$fee         = new stdClass();
		$fee->name   = kc_meta_to_label( $extra );
		$fee->amount = kc_get_order_extra_price( $extra, $orderId );
		$fee->tax    = 0;


		$order->add_fee( $fee );

		update_post_meta( $orderId, $extra, 1 );


		//Update Order Price
		$currentRemaining = (float) get_post_meta( $orderId, '_wc_deposits_remaining', true );
		$newRemaining     = $currentRemaining + $fee->amount;
		//update_post_meta( $orderId, '_wc_deposits_remaining', $newRemaining );
		//update_post_meta( $orderId, '_wc_deposits_remaining_paid', "no" );
	}


	//Remove old fees/meta
	foreach ( $existingFees as $feeId => $fee ) {
		$userRemoved = true;

		foreach ( $extras as $extra ) {
			if ( strtolower($fee['name']) == strtolower(kc_meta_to_label( $extra )) || ! kc_user_removable( $fee['name'], $orderId ) ) {
				$userRemoved = false;
			}
		}

		if ( $userRemoved ) {
			$removeExtras[ $feeId ] = $fee;
		}
	}

	foreach ( $removeExtras as $extraId => $extra ) {


		wc_delete_order_item( $extraId );
		update_post_meta( $orderId, kc_label_to_meta( $extra['name'] ), 0 );

		//Update Order Price
		$currentRemaining = (float) get_post_meta( $orderId, '_wc_deposits_remaining', true );
		$remaining        = $currentRemaining - (float) $extra['line_total'];
		//update_post_meta( $orderId, '_wc_deposits_remaining', $remaining );

	}

	if ( (float) ( get_post_meta( $orderId, '_wc_deposits_remaining', true ) ) > 0 ) {

		//$order->update_status( 'partially-paid' );
		//update_post_meta( $orderId, '_wc_deposits_remaining_paid', 'no' );

	}

	if($order->get_status() == "completed" || $order->get_status() == "processing") {
			$remaining = 0;
		} elseif ($order->get_status() == "partially-paid") {
			$remaining = (float) get_post_meta( $orderId, '_wc_deposits_remaining', true );
		} elseif ($order->get_status() == "pending") {
			$remaining = $order->get_total();
		}

       	global $woocommerce;
	$WC_Mailer = $woocommerce->mailer();

	do_action('account_extras_change', $orderId);

	echo json_encode( array(
		'status' => 200,
		'price'  => number_format( $remaining, 2 )
	), JSON_FORCE_OBJECT );
	die();
}


function kc_user_removable( $fee, $orderId ) {

	$order = new WC_Order( $orderId );

	foreach ( $order->get_items() as $cart_item_key => $cart_item ) {

		$productID = $cart_item['product_id'];

		$extras = get_field( 'available_extras', $productID );

		if ( $extras ) {

			foreach ( $extras as $existingExtra ) {


				$extraName = $existingExtra->post_title;

				if ( $fee == $extraName ) {
					return true;
				}

			}

		}

	}

	return false;

}

function kc_get_order_extra_price( $extra, $orderId ) {

	$price = 0;

	$order = new WC_Order( $orderId );

	foreach ( $order->get_items() as $cart_item_key => $cart_item ) {

		$productID = $cart_item['product_id'];

		$extras = get_field( 'available_extras', $productID );

		if ( $extras ) {

			foreach ( $extras as $existingExtra ) {


				$metaName = kc_label_to_meta( $existingExtra->post_title );

				if ( $metaName == $extra ) {
					return get_field( 'price', $existingExtra->ID );
				}


			}

		}

	}

	return $price;
}

function kc_is_hidden_extra( $metaName ) {


	$extra = get_page_by_title( kc_meta_to_label( $metaName ), 'OBJECT', 'retreat_extras' );


	if ( get_field( 'hidden_extra', $extra->ID ) ) {
		return true;
	}


	return false;

}

function kc_get_extras_from_order( $orderId ) {


	$extras_meta = get_post_meta( $orderId );
	$extras      = array();

	foreach ( $extras_meta as $meta => $value ) :

		$value = $value[0];

		if ( strpos( $meta, 'kc_extra' ) !== 0 || kc_is_hidden_extra( $meta ) || $value === "0" ) {
			continue;
		}

		$valueArray = unserialize( $value );
		$label      = kc_meta_to_label( $meta );

		if ( $valueArray ) {


			if ( $valueArray['hidden'] ) {
				continue;
			}

			$meta = $valueArray['name'];

		}

		$extras[] = kc_meta_to_label( $meta );

	endforeach;


	return $extras;
}
