<?php
/**
 * Шапка транзакційного листа.
 *
 * Пошта не вміє webfont'ів надійно, тож дисплейний шрифт замінений
 * на жирний системний. Усе інше — той самий бренд: Roast, Cherry
 * і надрядкове «22°».
 *
 * @package BRIX
 *
 * @var string $email_heading Заголовок листа.
 */

defined( 'ABSPATH' ) || exit;

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( get_bloginfo( 'name', 'display' ) ); ?></title>
</head>
<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">
	<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
		<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
			<tr>
				<td align="center" valign="top">
					<div id="template_header_image">
						<?php
						$brix_logo = get_option( 'woocommerce_email_header_image' );

						if ( $brix_logo ) {
							echo '<p style="margin-top:0"><img src="' . esc_url( $brix_logo ) . '" alt="' . esc_attr( get_bloginfo( 'name', 'display' ) ) . '" /></p>';
						}
						?>
					</div>

					<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_container">
						<tr>
							<td align="center" valign="top">
								<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_header">
									<tr>
										<td id="header_wrapper">
											<?php if ( ! $brix_logo ) : ?>
												<p class="brix-mark">
													BRIX<sup>22°</sup>
												</p>
											<?php endif; ?>
											<h1><?php echo esc_html( $email_heading ); ?></h1>
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<tr>
							<td align="center" valign="top">
								<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_body">
									<tr>
										<td valign="top" id="body_content">
											<table border="0" cellpadding="20" cellspacing="0" width="100%">
												<tr>
													<td valign="top">
														<div id="body_content_inner">
