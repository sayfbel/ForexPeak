<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmation</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <script>
        // Check URL for status parameter
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');

        if (status === 'success') {
            Swal.fire({
                title: 'Success!',
                text: 'Your account has been created successfully.',
                icon: 'success'
            }).then(function() {
                window.location = '../Views/index.php';
            });
        } else {
            // Handle other statuses or errors if needed
            Swal.fire({
                title: 'Error!',
                text: 'There was an issue with the registration.',
                icon: 'error'
            }).then(function() {
                window.location = '../Views/index.php';
            });
        }
    </script>
</body>
</html>
