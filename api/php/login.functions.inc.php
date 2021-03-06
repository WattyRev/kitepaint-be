<?php

#### Login Functions #####

function checkLogin($u, $p){
    $conn = connectToDb();
    global $seed; // global because $seed is declared in the header.php file


    $response = (object) array();
    $response->valid = true;

    if (!valid_username($u))
    {
        $response->valid = false;
        $response->message = 'Invalid username.';
        return $response;
    }
    if (!valid_password($p))
    {
        $response->valid = false;
        $response->message = 'Invalid password.';
        return $response;
    }
    if (!user_exists($u))
    {
        $response->valid = false;
        $response->message = 'User could not be found.';
        return $response;
    }

    //Now let us look for the user in the database.
    $query = sprintf("
        SELECT loginid
        FROM login
        WHERE
        username = '%s' AND password = '%s'
        AND disabled = 0 AND activated = 1
        LIMIT 1;", mysqli_real_escape_string($conn, $u), mysqli_real_escape_string($conn, sha1($p . $seed)));
    $result = mysqli_query($conn, $query);
    // If the database returns a 0 as result we know the login information is incorrect.
    // If the database returns a 1 as result we know  the login was correct and we proceed.
    // If the database returns a result > 1 there are multple users
    // with the same username and password, so the login will fail.
    if (mysqli_num_rows($result) == 0) {
        $response->valid = false;
        $response->message = 'An account could not be found.';
        return $response;
    } elseif (mysqli_num_rows($result) != 1) {
        $response->valid = false;
        $response->message = 'Multiple accounts were found, so you could not be logged in, please contact us to resolve the issue.';
        return $response;
    } else {

        //Check to see if the user has been deleted
        $query = sprintf("
            SELECT deleted
            FROM login
            WHERE
            username = '%s' AND password = '%s'
            AND disabled = 0 AND activated = 1
            LIMIT 1;", mysqli_real_escape_string($conn, $u), mysqli_real_escape_string($conn, sha1($p . $seed)));
        $deleted_result = mysqli_query($conn, $query);
        $deleted_row = mysqli_fetch_array($deleted_result);
        $deleted = $deleted_row['deleted'];

        if ($deleted === "1") {
            $response->valid = false;
            $response->message = 'This account has been deleted';
            return $response;
        }

        // Login was successfull
        $row = mysqli_fetch_array($result);

        $loginid = $row['loginid'];

        $query = sprintf("
            SELECT actcode
            FROM login
            WHERE
            loginid = '%s'
            LIMIT 1;", mysqli_real_escape_string($conn, $loginid));

        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_array($result);

        $actcode = $row['actcode'];

        $query = sprintf("
            SELECT email
            FROM login
            WHERE
            loginid = '%s'
            LIMIT 1;", mysqli_real_escape_string($conn, $loginid));

        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_array($result);

        $email = $row['email'];

        $query = sprintf("
            SELECT favorites
            FROM login
            WHERE
            loginid = '%s'
            LIMIT 1;", mysqli_real_escape_string($conn, $loginid));

        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_array($result);

        $favorites = $row['favorites'];

        $query = sprintf("
            SELECT first_name
            FROM login
            WHERE
            loginid = '%s'
            LIMIT 1;", mysqli_real_escape_string($conn, $loginid));

        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_array($result);

        $first_name = $row['first_name'];

        $query = sprintf("
            SELECT last_name
            FROM login
            WHERE
            loginid = '%s'
            LIMIT 1;", mysqli_real_escape_string($conn, $loginid));

        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_array($result);

        $last_name = $row['last_name'];

        //update last login time
        $query = sprintf("update login set last_login = now() where loginid = '%s'",
            mysqli_real_escape_string($conn, $loginid));

        if (mysqli_query($conn, $query)) {
        } else {
            $response->valid = false;
            $response->message = 'Unable to log in';
            return $response;
        }

        // Save the user ID for use later
        $_SESSION['loginid'] = $loginid;
        // Save the username for use later
        $_SESSION['username'] = $u;
        //save act code for use later
        $_SESSION['actcode'] = $actcode;
        //save email
        $_SESSION['email'] = $email;
        //save favorites
        $_SESSION['favorites'] = $favorites;
        //save first name
        $_SESSION['first_name'] = $first_name;
        //save last name
        $_SESSION['last_name'] = $last_name;
        // Now we show the userbox
        return $response;
    }
}

function updateLogin($username, $loginid, $actcode) {
    $conn = connectToDb();
    $response = (object) array();
    $response->valid = true;

    if (!valid_username($username) || !user_exists($username))
    {
        $response->valid = false;
        $response->message = 'Invalid username';
        return $response;
    }

    //Now let us look for the user in the database.
    //get email
    $query = sprintf("
        SELECT email
        FROM login
        WHERE
        username = '%s' AND loginid = '%s'
        AND actcode = '%s'
        LIMIT 1;", mysqli_real_escape_string($conn, $username), mysqli_real_escape_string($conn, $loginid), mysqli_real_escape_string($conn, $actcode));

    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) != 1) {
        $response->valid = false;
        $response->message = 'Invalid credentials';
        return $response;
    }

    $row = mysqli_fetch_array($result);
    $email = $row['email'];

    //get favorites
    $query = sprintf("
        SELECT favorites
        FROM login
        WHERE
        username = '%s' AND loginid = '%s'
        AND actcode = '%s'
        LIMIT 1;", mysqli_real_escape_string($conn, $username), mysqli_real_escape_string($conn, $loginid), mysqli_real_escape_string($conn, $actcode));

    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) != 1) {
        $response->valid = false;
        $response->message = 'Invalid credentials';
        return $response;
    }

    $row = mysqli_fetch_array($result);
    $favorites = $row['favorites'];

    //first_name
    $query = sprintf("
        SELECT first_name
        FROM login
        WHERE
        username = '%s' AND loginid = '%s'
        AND actcode = '%s'
        LIMIT 1;", mysqli_real_escape_string($conn, $username), mysqli_real_escape_string($conn, $loginid), mysqli_real_escape_string($conn, $actcode));

    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) != 1) {
        $response->valid = false;
        $response->message = 'Invalid credentials';
        return $response;
    }

    $row = mysqli_fetch_array($result);
    $first_name = $row['first_name'];

    //last_name
    $query = sprintf("
        SELECT last_name
        FROM login
        WHERE
        username = '%s' AND loginid = '%s'
        AND actcode = '%s'
        LIMIT 1;", mysqli_real_escape_string($conn, $username), mysqli_real_escape_string($conn, $loginid), mysqli_real_escape_string($conn, $actcode));

    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) != 1) {
        $response->valid = false;
        $response->message = 'Invalid credentials';
        return $response;
    }

    $row = mysqli_fetch_array($result);
    $last_name = $row['last_name'];

    $query = sprintf("update login set last_login = now() where loginid = '%s'",
            mysqli_real_escape_string($conn, $loginid));

    if (mysqli_query($conn, $query)) {
        $_SESSION['username'] = $username;
        $_SESSION['loginid'] = $loginid;
        $_SESSION['actcode'] = $actcode;
        $_SESSION['email'] = $email;
        $_SESSION['favorites'] = $favorites;
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name;
        return $response;
    } else {
        $response->valid = false;
        $response->message = 'Unable to login';
        return $response;
    }
}

?>
