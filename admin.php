<?php

require 'vendor/autoload.php';
use sandeepshetty\shopify_api;
include 'shopifyFunctions.php';
include 'includes/mySQLconnect.php';
include 'includes/config.php';

session_start(); //start a session

// Prepare shopify data

	// get shop name
	$shop = $_SESSION['shop'];

	// get app and user data from databases
	$select_settings = $db->query(	"SELECT * FROM tbl_appsettings
			 						WHERE id = 1");
	$app_settings = $select_settings->fetch_object();

	$select_userSettings = $db->query(	"SELECT * FROM tbl_usersettings 
										WHERE store_name = '$shop'");
	$shop_data = $select_userSettings->fetch_object();

	//connect to shopify client
	$shopify = shopify_api\client(
	  $shop, $shop_data->access_token, $app_settings->api_key, $app_settings->shared_secret
	);


// Handling of unselect feature 
if ($_POST['submitUnselect']) { 
	
	// delete book from Shopify
		
		// get product ID where sku is equal to BookId
		$query= "SELECT ShopifyBookId FROM SelectedBooks 
				WHERE BookId = '" . $_POST['submitUnselect'] . "'";
		$call = $db->query($query);
			
			// echo if errors
			if (!$call){
				echo $db->error . '</br>'. '</br>';
			}
		
		$callArray = $call->fetch_array();	
		$productId = $callArray["ShopifyBookId"];
		
		// delete product with the according productID
		deleteProduct($productId);
	
	// remove book from SelectedBooks
	$query = 	"DELETE FROM SelectedBooks 
				WHERE BookId = '" . $_POST['submitUnselect'] . "'";
	$call = $db->query($query);

		// if errors echo them
		if (!$call){
			echo $db->error . '</br>'. '</br>';
		}
}

	
// Handling of update price feature
if ($_POST["submitRetailPrice"]) { 
	
	// calculate margin
		// get wholesale price
		$query= "SELECT Price FROM SelectedBooks 
				WHERE BookId = '" . $_POST['submitRetailPrice'] . "'";
		$call = $db->query($query);
		
			// echo if errors
			if (!$call){
				echo $db->error . '</br>'. '</br>';
			}

		$callArray = $call->fetch_array();	
		$wholesalePrice = json_decode($callArray["Price"]);
		$wholesalePrice = $wholesalePrice->_;

		// calculate margin
		$calcMargin = $_POST['retailPrice'] / $wholesalePrice;

	// update column in SelectedBooks
	$query = 	"UPDATE SelectedBooks 
				SET `retailPrice` = '" . $_POST['retailPrice'] . "' , `margin` = '" . $calcMargin . "' 
				WHERE BookId = '" . $_POST["submitRetailPrice"] . "'";
	$call = $db->query($query);

		// if errors echo them
		if (!$call){
			echo $db->error . '</br>'. '</br>';
		}
		
	// update product on Shopify
	
		// get product ID where sku is equal to BookId
		$query= "SELECT ShopifyBookId, Title FROM SelectedBooks 
				WHERE BookId = '" . $_POST['submitRetailPrice'] . "'";
		$call = $db->query($query);
		
			// echo if errors
			if (!$call){
				echo $db->error . '</br>'. '</br>';
			}
			
		$callArray = $call->fetch_array();	
		$productId = $callArray["ShopifyBookId"];
			
		// get products variants ID
		$array = array("fields"=>"variants"); 
		$productsArray = getProductById($productId, $array);
		$variantsArray = $productsArray['variants'][0];
		$variantId = $variantsArray['id'];
				
		// insert new price in variant
		$arguments = array
		        (
		            "variant"=>array
		            (
						"price"=>$_POST['retailPrice']
		            )
		        );
				
		updateVariant($variantId,$arguments);
}
	
	
// Handling of select book feature 	
if ($_POST['submitBook']){
	
	// insert book into selected books
	
		// get column names from AllPublizonBooks
		$query="SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
				WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'AllPublizonBooks'";
		$call = $db->query($query);

			// if errors echo them
			if (!$call){
				echo $db->error . '</br>'. '</br>';
			}

		// make column name string
		$columns = array();
		while ($row = $call->fetch_array()) {
			$columns[] = $row[0];	
		}
		$columns = implode(', ', $columns);
	
		// insert book data from AllPublizonBooks into SelectedBooks table
		$titel =  $_POST['Titel'];
		$isbn = $_POST['ISBN'];
		$query = 	"INSERT INTO `SelectedBooks` (" . $columns . ") 
					SELECT " . $columns . " FROM `AllPublizonBooks` 
					WHERE Title= '". $titel ."' AND Identifier = '" . $isbn . "'";	
		$call = $db->query($query);

			// if errors echo them
			if (!$call){
				echo $db->error . '</br>'. '</br>';
			}


		// insert standard margin and calculated standard price into selected books table
			// get standard margin from database
			$query = "SELECT generalGrossMargin FROM `BusinessSettings`";
			$standardMargin = $db->query($query);

				// if errors echo them
				if (!$selectedBooksObject){
					echo $db->error . '</br>'. '</br>';
				}	

			$standardMargin = $standardMargin->fetch_array();
			$standardMargin = $standardMargin["generalGrossMargin"];

			// get wholesale price from database 
			$query="SELECT Price FROM SelectedBooks WHERE Title= '". $titel ."' AND Identifier = '" . $isbn . "'";
			$call = $db->query($query);

				// echo if errors
				if (!$call){
					echo $db->error . '</br>'. '</br>';
				}

			$wholesalePrice = $call->fetch_array();
			$wholesalePrice = json_decode($wholesalePrice["Price"]);
			$wholesalePrice = $wholesalePrice->_;
			
			// calculate standard price
			$standardPrice = $wholesalePrice * $standardMargin ;

			//insert into selected books
			$query = 	"UPDATE `SelectedBooks` 
						SET margin = '". $standardMargin ."' , retailPrice = '". $standardPrice ."'
						WHERE Title= '". $titel ."' AND Identifier = '" . $isbn . "' ";

			$call = $db->query($query);

				// if errors echo them
				if (!$call){
					echo $db->error . '</br>'. '</br>';
				}

	
	// add books to shopify
	
		// get book data from Selected Books
		$query="SELECT * FROM SelectedBooks 
				WHERE Title= '". $titel ."' AND Identifier = '" . $isbn . "'";
		$call = $db->query($query);
	
			// echo if errors
			if (!$call){
				echo $db->error . '</br>'. '</br>';
			}
	
		// declare variables corresponding to each column
		foreach ($call->fetch_array() as $key => $value) {
			${$key} = $value;		
		}
	
		// prepare values to be inserted 
			//prepare images
			$images = json_decode($Images, true);
			$images=$images['Image'][0];
			$images=array_chunk($images,1);
			$images=$images[0];
			$images['src'] = $images[0];
			unset($images[0]);
	
			//prepare main description
			$MainDescription = addslashes($MainDescription);
	
			//Prepare variants array 
			$variants = array();
			$variants['option1'] = $Title;
			$variants['price'] = $retailPrice;
			$variants['sku'] = $BookId ;
		
			// prepare subjects 
			$subjects = json_decode($Subjects,true);
					$subjects = $subjects["SimpleSubject"];
				
					// Check how deep array is
					function array_depth(array $array) {
					    $max_depth = 1;

					    foreach ($array as $value) {
					        if (is_array($value)) {
					            $depth = array_depth($value) + 1;

					            if ($depth > $max_depth) {
					                $max_depth = $depth;
					            }
					        }
					    }
					
					    return $max_depth;
					}
					$arrayDepth = array_depth($subjects);
					
					// make data into string			
					if ($arrayDepth==1){
						// correct encoding
						$subject = correctEncoding($subjects['Description']);
						// group tag
						$subject = groupTag($subject);
						$subjectsString = $subject;
					}
				
					if ($arrayDepth==2) {
						$subjectsArray = array();
						foreach ($subjects as $subject) {

							// correct encoding
							$subject = correctEncoding($subject['Description']);
							// group tags
							$subject = groupTag($subject);
							// insert into array 
							$subjectsArray[] = $subject;
						}
						$subjectsString = implode($subjectsArray, ", ");
					} 
	
		// insert data into arguments array
		$arguments = array
			        (
			            "product"=>array
			            (
							"title" => $Title,
							"body_html" => $MainDescription,
							"product_type"=> $BookType,
							"images"=>$images,
							"variants"=>$variants,
							"tags"=>$subjectsString
			            )
			        );
					
		// create new product
		createProduct($arguments);
	
	// store product ID in selected books table
	
		// get data from shopify 
		$array = array("fields"=>"id, variants"); 
		$productsArray = getProduct($array);
		
		foreach ($productsArray as $productArray){
			$productId = $productArray['id'];
			$productSku = $productArray['variants'][0]['sku'];
			
			// insert product ID into database
			$query = 	"UPDATE SelectedBooks 
						SET `ShopifyBookId` = '" . $productId . "' 
						WHERE BookId = '" . $productSku . "'";							
			$call = $db->query($query);

				// if errors echo them
				if (!$call){
					echo $db->error . '</br>'. '</br>';
				}
		}
}

// handling of submit margin
if ($_POST["margin"]) { 
	
	// update column in SelectedBooks
	$query = 	"UPDATE SelectedBooks 
				SET `margin` = '" . $_POST['margin'] . "'
				WHERE `BookID` = '" . $_POST['submitMargin'] . "' ";
	$call = $db->query($query);

		// if errors echo them
		if (!$call){
			echo $db->error . '</br>'. '</br>';
		}
	
	// get wholesale price from database

	$query= "SELECT Price FROM SelectedBooks 
			WHERE BookId = '" . $_POST['submitMargin'] . "'";
	$call = $db->query($query);
	
		// echo if errors
		if (!$call){
			echo $db->error . '</br>'. '</br>';
		}

	$callArray = $call->fetch_array();	
	$wholesalePrice = json_decode($callArray["Price"]);
	$wholesalePrice = $wholesalePrice->_;


	// calculate new retail price

	$retailPrice = $wholesalePrice * $_POST['margin'] ;


	// insert new retail price in database

	$query = 	"UPDATE SelectedBooks 
				SET `retailPrice` = '" . $retailPrice . "'
				WHERE `BookID` = '" . $_POST['submitMargin'] . "' ";

	$call = $db->query($query);

		// if errors echo them
		if (!$call){
			echo $db->error . '</br>'. '</br>';
		}

	// insert in shopify

		// get product ID where sku is equal to BookId
		$query= "SELECT ShopifyBookId, Title FROM SelectedBooks 
				WHERE BookId = '" . $_POST['submitMargin'] . "'";
		$call = $db->query($query);
		
			// echo if errors
			if (!$call){
				echo $db->error . '</br>'. '</br>';
			}
			
		$callArray = $call->fetch_array();	
		$productId = $callArray["ShopifyBookId"];
			
		// get products variants ID
		$array = array("fields"=>"variants"); 
		$productsArray = getProductById($productId, $array);
		$variantsArray = $productsArray['variants'][0];
		$variantId = $variantsArray['id'];
				
		// insert new price in variant
		$arguments = array
		        (
		            "variant"=>array
		            (
						"price"=>$retailPrice
		            )
		        );
				
		updateVariant($variantId,$arguments);


}

// handling of reset margin
if ($_POST["resetMargin"]) { 
	
	// get standard margin
	$query= "SELECT generalGrossMargin FROM BusinessSettings";
	$call = $db->query($query);
	$callArray = $call->fetch_array();	
	$standardMargin = $callArray[0];

	// update column in SelectedBooks
	$query = 	"UPDATE SelectedBooks 
				SET `margin` = '" . $standardMargin . "'
				WHERE `BookID` = '" . $_POST['resetMargin'] . "' ";
	$call = $db->query($query);

		// if errors echo them
		if (!$call){
			echo $db->error . '</br>'. '</br>';
		}
	
	// get wholesale price from database

	$query= "SELECT Price FROM SelectedBooks 
			WHERE BookId = '" . $_POST['resetMargin'] . "'";
	$call = $db->query($query);
	
		// echo if errors
		if (!$call){
			echo $db->error . '</br>'. '</br>';
		}

	$callArray = $call->fetch_array();	
	$wholesalePrice = json_decode($callArray["Price"]);
	$wholesalePrice = $wholesalePrice->_;


	// calculate new retail price

	$retailPrice = $wholesalePrice * $standardMargin ;


	// insert new retail price in database

	$query = 	"UPDATE SelectedBooks 
				SET `retailPrice` = '" . $retailPrice . "'
				WHERE `BookID` = '" . $_POST['resetMargin'] . "' ";

	$call = $db->query($query);

		// if errors echo them
		if (!$call){
			echo $db->error . '</br>'. '</br>';
		}

	// insert in shopify

		// get product ID where sku is equal to BookId
		$query= "SELECT ShopifyBookId, Title FROM SelectedBooks 
				WHERE BookId = '" . $_POST['resetMargin'] . "'";
		$call = $db->query($query);
		
			// echo if errors
			if (!$call){
				echo $db->error . '</br>'. '</br>';
			}
			
		$callArray = $call->fetch_array();	

		$productId = $callArray["ShopifyBookId"];

		
		// get products variants ID
		$array = array("fields"=>"variants"); 
		$productsArray = getProductById($productId, $array);
		$variantsArray = $productsArray['variants'][0];
		$variantId = $variantsArray['id'];
			
		// insert new price in variant
		$arguments = array
		        (
		            "variant"=>array
		            (
						"price"=>$retailPrice
		            )
		        );
				
		updateVariant($variantId,$arguments);

}

// Handling of update standard margin feature
if ($_POST["submitStandardMargin"]) { 
	
	// update column in SelectedBooks
	$query = "UPDATE BusinessSettings SET `generalGrossMargin` = '" . $_POST['standardMargin'] . "'";
	$call = $db->query($query);

		// if errors echo them
		if (!$call){
			echo $db->error . '</br>'. '</br>';
		}
	
}


// import books from selected table to view.
$query = "SELECT * FROM `SelectedBooks`";
$selectedBooksObject = $db->query($query);

	// if errors echo them
	if (!$selectedBooksObject){
		echo $db->error . '</br>'. '</br>';
	}	

// import margin to view

$query = "SELECT generalGrossMargin FROM `BusinessSettings`";
$standardMargin = $db->query($query);

	// if errors echo them
	if (!$selectedBooksObject){
		echo $db->error . '</br>'. '</br>';
	}	

$standardMargin = $standardMargin->fetch_array();
$standardMargin = $standardMargin["generalGrossMargin"];
	

?>

<html>

	<head>
		<meta charset="utf-8"> 
		<link rel="stylesheet" type="text/css" href="CSS/stylesheet.css">
		<script type="text/javascript" src="js/jquery.js"></script>
		<script type="text/javascript"></script>
		<script src="js/jqueryscript.js"></script>
	</head>
	
	<body>
		
		<div>
			<form id="book" name="book" method="post" action="">			
				
					<label> Titel
						<input type="text" name="Titel" id="Titel"/>
					</label>			
				
					<label> ISBN
						<input type="text" name="ISBN" id="ISBN"/>
					</label>
			
					<label>
						<input type="submit" name="submitBook" id="submit" value="Submit"/>
					</label>
			
			</form>

			<form id="standardMargin" name="standardMargin" method="post" action="">			
				
					<label> Standard margin
						<input type="text" name="standardMargin" id="standardMargin" value= "<?php echo $standardMargin; ?> "/>
					</label>
			
					<label>
						<input type="submit" name="submitStandardMargin" id="submit" value="Submit"/>
					</label>
			
			</form>

		</div>
		
		<?php while ($row = $selectedBooksObject->fetch_array()) {?>
		
			<div class = 'task'>
		
				<form id="unselect" name="unselect" method="post" action="">		
						<button type="submitUnselect" name="submitUnselect" value="<?php echo $row['BookId'] ;?>">Remove</button>
				</form>	
		
				<?php
				$wholesalePrice = json_decode($row["Price"]);
				echo $row["Title"]. "&nbsp;&nbsp;&nbsp;&nbsp af " . $row["Authors"];
				echo " &nbsp;&nbsp;&nbsp;&nbsp;" . "engros pris: " . $wholesalePrice->_ . " " . $wholesalePrice->CurrencyCode;	
				?>

				<form id="margin" name="margin" method="post" action="">
			
					<label> margin:
						<input type="text" name="margin" id="margin" value = "<?php echo $row['margin']; ?>"/>
					</label>			

					<label>
						<button type="submitMargin" name="submitMargin" value="<?php echo $row["BookId"]; ?>" >Submit</button>
					</label>	

				</form>	

				<form id="retailPrice" name="retailPrice" method="post" action="">
			
					<label> detail pris:
						<input type="text" name="retailPrice" id="retailPrice" value = "<?php echo $row["retailPrice"]; ?>"/>
					</label>			
	
					<label>
						<button type="submitRetailPrice" name="submitRetailPrice" value="<?php echo $row["BookId"]; ?>" >Submit</button>
					</label>	

				</form>

				<form id="resetMargin" name="resetMargin" method="post" action="">

					<label>
						<button type="resetMargin" name="resetMargin" value="<?php echo $row["BookId"]; ?>" >Reset Margin</button>
					</label>	

				</form>	
			
			</div>
			
		<?php } ?>

	</body>
	
</html>	
