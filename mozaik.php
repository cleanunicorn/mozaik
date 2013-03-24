<?php

$original_image = 'f3.jpg';
$directory_with_images = 'poze/';
$tile_size = 4;

$size_koef = 1;

$file_cache = 'picture_data.txt';
$images_md5 = array();
if ( $file_cache_handle = fopen( $file_cache, "r" ) )
{
	$images_md5_encoded = file_get_contents( $file_cache );
	$images_md5 = json_decode( $images_md5_encoded, true );
}

$image_res_temp = array();
$image_res_count = 0;

// $directory_with_images_processed = 'poze_processed/'; //Square images

$images_coef = array();

function check_closest( $Close_to_me, $Data_array )
{
	$best_diff = 99999999;
	$best_match = '';
	foreach( $Data_array as $file=>$data )
	{
		$dr = $Close_to_me["r"] - $data["r"];
		$dg = $Close_to_me["g"] - $data["g"];
		$db = $Close_to_me["b"] - $data["b"];
		
		$diff = $dr * $dr + $dg * $dg + $db * $db;
		
		if ( $best_diff > $diff )
		{
			$best_match = $file;
			$best_diff = $diff;
		}
	}
	
	return array( 
		"file" =>	$best_match, 
		"coef" =>	$best_diff 
		);
}

echo "Processing the images \n";

$dir_images_handle = opendir( $directory_with_images );
while( false !== ( $file = readdir( $dir_images_handle ) ) )
{
	if ( ( $file != '.' ) && ( $file != '..' ) )
	{
		$image_extension = substr( $file, strrpos( $file, "." ) + 1 );
		$image_path = realpath( $directory_with_images . $file );
		
		echo "Computing md5";
		$image_md5 = md5_file( $image_path );
		echo " done\n";
		
		if ( isset( $images_md5[$image_md5] ) )
		{
			echo "Already processed, skipping \n";
			$images_coef[$file] = $images_md5[$image_md5];
			
			//print_r( $images_coef[$file] );
			//print_r( $images_md5[$image_md5] );
			
			continue;
		}
		echo "Not found, will process file \n";
		
		switch( strtolower( $image_extension ) )
		{
			case 'jpg':
			case 'jpeg':
				$image_res = imagecreatefromjpeg( $image_path );
				break;
			case 'png':
				$image_res = imagecreatefrompng( $image_path );
				break;
			default:
				continue;
				break;
		}
		
		$iw = imagesx( $image_res );
		$ih = imagesy( $image_res );
		$pixel_number = $iw * $ih;
		
		$color_sum = array();
		$color_r = 0;
		$color_g = 0;
		$color_b = 0;
		for( $i = 0 ; $i < $iw ; $i++ )
		{
			for( $j = 0 ; $j < $ih ; $j++ )
			{
				$colors = imagecolorat( $image_res, $i, $j );
				$colors = imagecolorsforindex( $image_res, $colors );
				$color_r += $colors["red"];
				$color_g += $colors["green"];
				$color_b += $colors["blue"];
			}
		}
		
		$color_r = $color_r / $pixel_number;
		$color_g = $color_g / $pixel_number;
		$color_b = $color_b / $pixel_number;
		
		$images_md5[$image_md5] = $images_coef[$file] = array(
			'r'	=>	(int)$color_r,
			'g'	=>	(int)$color_g,
			'b'	=>	(int)$color_b
		);
		
		echo "$file \n";
		echo "\n";
	}
}

$image_original_res = imagecreatefromjpeg( realpath( $original_image ) );
$image_w = imagesx( $image_original_res );
$image_h = imagesy( $image_original_res );

if ( $file_cache_handle )
{
	fclose( $file_cache_handle );
}
$file_cache_handle = fopen( $file_cache, "w" );
if ( $file_cache_handle )
{
	fprintf( $file_cache_handle, json_encode( $images_md5 ) );
	fclose( $file_cache_handle );
}
else
{
	echo $file_cache_handle;
	exit;
}

echo "\n";
echo realpath( $original_image ) ."\n";
echo "Width $image_w \n";
echo "Height $image_h \n";

$image_generated_res = imagecreate( $image_w * $size_koef, $image_h * $size_koef );
$background_color = imagecolorallocate($image_generated_res, 0, 0, 0);



for( $i = 0 ; $i < $image_w ; $i += $tile_size )
{
	for( $j = 0 ; $j < $image_h ; $j += $tile_size )
	{
		echo "\t". ( $i / $tile_size ) ." / \t". ( $image_w / $tile_size ) ."\n";
		echo "\t". ( $j / $tile_size ) ." / \t". ( $image_h / $tile_size ) ."\n";
		$tw = $i + $tile_size;
		$th = $j + $tile_size;
		if ( $tw > $image_w )
		{
			$tw = $image_w;
		}
		if ( $th > $image_h )
		{
			$th = $image_h;
		}
		
		$tile_pixel_number = 0;
		
		$tile_color_r = 0;
		$tile_color_g = 0;
		$tile_color_b = 0;
		
		echo "Computing tile ...";
		
		for ( $x = $i ; $x < $tw ; $x++ )
		{
			for ( $y = $j ; $y < $th ; $y++ )
			{
				$t_colors = imagecolorat( $image_original_res, $x, $y );
				$t_colors = imagecolorsforindex( $image_original_res, $t_colors );

				$tile_color_r += $t_colors["red"];
				$tile_color_g += $t_colors["green"];
				$tile_color_b += $t_colors["blue"];
				
				$tile_pixel_number++;
			}
		}
		
		echo " done \n";
		
		$tile_color_r = $tile_color_r / $tile_pixel_number;
		$tile_color_g = $tile_color_g / $tile_pixel_number;
		$tile_color_b = $tile_color_b / $tile_pixel_number;
		
		$tile_color = array(
			"r"	=> (int)$tile_color_r,
			"g"	=> (int)$tile_color_g,
			"b"	=> (int)$tile_color_b
		);
		
		$closest_result = check_closest( $tile_color, $images_coef );
		
		echo "Creating image resource ...";
		
		//Draw closest result
		$image_extension = substr( $closest_result["file"], strrpos( $closest_result["file"], "." ) + 1 );
		$image_path = realpath( $directory_with_images . $closest_result["file"] );
		
		if ( !isset( $image_res_temp[$image_path] ) )
		{	
			echo " not found ... ";
			switch( strtolower( $image_extension ) )
			{
				case 'jpg':
				case 'jpeg':
					$image_res_temp_aux = imagecreatefromjpeg( $image_path );
					break;
				case 'png':
					$image_res_temp_aux = imagecreatefrompng( $image_path );
					break;
				default:
					echo "NO EXT! \n";
					print_r( $closest_result );
					exit;
					break;
			}
			
		}
		else
		{
			echo " resource exists, skipping ... ";
		}
		
		$iw_temp = imagesx( $image_res_temp_aux );
		$ih_temp = imagesy( $image_res_temp_aux );	
		
		echo " done \n";
		
		echo "Copy tile ... ";
		
		imagecopyresampled( 
			$image_generated_res, 
			$image_res_temp_aux, 
			$i * $size_koef, $j * $size_koef, 
			0, 0,
			( $tw - $i ) * $size_koef,
			( $th - $j ) * $size_koef,
			$iw_temp,
			$ih_temp
			);
	
		echo " done \n";
		echo "--- \n";
	}
}

echo "Finished processing\n";

/*
imagecopymerge(
	$image_res_temp,
	$image_original_res,
	0, 0,
	0, 0,
	$image_w, $image_h,
	50
);
*/

echo "Writing file ";
//$wrote = imagejpeg( $image_generated_res, 'file_'. $tile_size .'.jpeg', 100 );
$wrote = imagepng( $image_generated_res, 'file_'. $tile_size .'.jpeg', 9 );
echo " $wrote \n ";

?>
