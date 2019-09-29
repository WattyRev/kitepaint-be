<?php
require_once "header.php";

function getProducts($filter, $return) {
    $query = "";
	if(isset($filter)){
		$query .= "WHERE";
		$count = 0;
		foreach($filter as $metric => $value){
			$count ++;
			if ($count > 1) {
				$query .= ' AND ';
			}
			$query .= "$metric  =  $value";
		}
	}
	$query = sprintf("SELECT * FROM products $query");

	$result = mysql_query($query);
	$num = mysql_num_rows($result);
	$response = array();
	for ($i = 0; $i < $num; $i++) {
        $productId = mysql_result($result,$i,'id');
        echo "Processing product with ID $productId \n";
		$product = (object) array();
		foreach ($return as $key=>$metric){
            echo "  Processing metric $metric \n";
            if ($metric === 'variations') {
                $variationsQuery = sprintf("SELECT * FROM variations WHERE productId = $productId ORDER BY sortIndex");
            	$variationsResult = mysql_query($variationsQuery);
            	$variationsNum = mysql_num_rows($variationsResult);
                $variations = array();
                for($i = 0; $i < $variationsNum; $i++) {
                    $variation = (object) array();
                    $variation->id = mysql_result($variationsResult,$i,'id');
                    $variation->name = mysql_result($variationsResult,$i,'name');
                    // $variation->svg = mysql_result($variationsResult,$i,'svg');
                    $variation->sortIndex = mysql_result($variationsResult,$i,'sortIndex');
                    array_push($variations, $variation);
                }
                $product->variations = $variations;
            }
			$product->$metric = mysql_result($result,$i,$metric);
			if ($metric === 'created') {
				$product->$metric = date("m/d/Y", strtotime($product->$metric));
			}
		}
		array_push($response, $product);
	}
	mysql_close();
	return JSON_encode($response);
}

function deleteProduct($id) {
    $response = (object) array(
		'valid' => true,
		'message' => ''
	);

    $query = sprintf("DELETE FROM products WHERE id = '%s'",
        mysql_real_escape_string($id));

    if (!mysql_query($query)) {
        $response->valid = false;
        $response->message = 'Unable to delete product';
    }

    // Delete relevant variations
    $query = sprintf("DELETE FROM variations WHERE productId = '%s'",
        mysql_real_escape_string($id));

    if (!mysql_query($query)) {
        $response->valid = false;
        $response->message = 'Unable to delete variations';
    }

    return json_encode($response);
}

function createProduct($postData) {
    $response = (object) array(
		'valid' => true,
		'message' => ''
	);

    $name = $postData['name'];
    $manufacturer = $postData['manufacturer'];
    $url = $postData['url'];
    $colors = $postData['colors'];
    $variations = json_decode($postData['variations']);
    $notes = $postData['notes'];
    $embed = $postData['embed'];

    // Create the product
    $sql = sprintf("insert into products (status,name,manufacturer,created,url,colors) value (0,'%s','%s',now(),'%s','%s','%s')",
    mysql_real_escape_string($name), mysql_real_escape_string($manufacturer)
    , mysql_real_escape_string($url), mysql_real_escape_string($colors));


    if (!mysql_query($sql)) {
        $response->valid = false;
        $response->message = 'Unable to create product';
        return json_encode($response);
    }

    // Create the variations
    $productId = mysql_insert_id();
    foreach($variations as $index=>$variation) {
        $sql = sprintf("INSERT INTO variations (name,svg,productId,sortIndex) value ('%s','%s','%s','%s')",
        mysql_real_escape_string($variation['name']), mysql_real_escape_string($variation['svg'])
        , mysql_real_escape_string($productId), mysql_real_escape_string($index));

        if (!mysql_query($sql)) {
            $response->warning = 'The product was created, but the creation of some variations may have failed.';
        }
    }

    // Return success
    return json_encode($response);
}

function updateProduct($postData) {
    $response = (object) array(
		'valid' => true,
		'message' => ''
	);
    $id = $postData['id'];
    $variations = $postData['variations'];
	$vars = array(
		'status' => $postData['status'],
		'name' => $postData['name'],
		'manufacturer' => $postData['manufacturer'],
		'url' => $postData['url'],
		'colors' => $postData['colors'],
		'notes' => $postData['notes'],
		'embed' => $postData['embed']
	);

	foreach($vars as $metric => $val){
		$query = sprintf("update products set $metric = '%s' where id = '%s'",
			mysql_real_escape_string($val), mysql_real_escape_string($id));

		if (!mysql_query($query)) {
			$response->valid = false;
			$response->message = 'Unable to change ' . $metric;
		}
	}

    // Update variations
    $touchedIds = array();
    $storedIds = array();
    foreach($variations as $index=>$variation) {
        $query = sprintf("SELECT id FROM variations WHERE productId = '%s'", mysql_real_escape_string($id));
    	$result = mysql_query($query);
    	$num = mysql_num_rows($result);
    	for ($i = 0; $i < $num; $i++) {
            $variationId = mysql_result($result,$i,'id');
    		array_push($variationIds, $variationId);
    	}


        // Update existing variation
        if (isset($variation['id'])) {
            $variationId = $variation['id'];
            array_push($touchedIds, $variationId);
            $sql = sprintf("update variations set name = '%s', svg = '%s', sortIndex = '%s' WHERE id = '%s'",
            mysql_real_escape_string($variation['name']), mysql_real_escape_string($variation['svg'], mysql_real_escape_string($index))
            , mysql_real_escape_string($variationid));
        } else {
            // Create new variation
            $sql = sprintf("insert into variations (name,svg,productId,sortIndex) value ('%s','%s','%s','%s')",
            mysql_real_escape_string($variation['name']), mysql_real_escape_string($variation['svg'])
            , mysql_real_escape_string($id), mysql_real_escape_string($index));
        }
    }
    // Delete unmentioned variations
    foreach ($storedIds as $storedId) {
        if (in_array($storedId, $touchedIds)) {
            return;
        }
        $sql = sprintf("DELETE from variations WHERE id = '%s'", mysql_real_escape_string($storedId));
    }


	return json_encode($response);
}

if ($_GET){
    $filter = isset($_GET['filter']) ? $_GET['filter'] : null;
    echo getProducts($filter, $_GET['return']);
    return;
}
if ($_POST) {
	$response = (object) array(
		'valid' => true,
		'message' => ''
	);

	//Delete
	if (isset($_POST['delete'])) {
		echo deleteProduct($_POST['id']);
        return;
	}

	//Create
	if (isset($_POST['new'])) {
        echo createProduct($POST);
        return;
	}

	//Update
    echo updateProduct($POST);
    return;
}

echo 'No GET or POST variables';
