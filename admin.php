<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LuxeStay | Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container mt-5">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-dark text-white p-4 rounded-top-4">
                <h3 class="mb-0">🏨 LuxeHotel Database Viewer</h3>
                <p class="mb-0 text-white-50">Current Reservations</p>
            </div>
            <div class="card-body p-4">

                <?php
                // 1. Database Connection Credentials
                $host = "localhost";
                $username = "root"; // Default XAMPP username
                $password = "";     // Default XAMPP password is empty
                $database = "luxehotel"; // Make sure you created this in phpMyAdmin

                // 2. Create the connection using MySQLi
                $conn = new mysqli($host, $username, $password, $database);

                // 3. Check connection
                if ($conn->connect_error) {
                    echo "<div class='alert alert-danger'><strong>Connection Failed:</strong> " . $conn->connect_error . "</div>";
                } else {

                    // 4. Query the database to get all bookings, joining with the Users and Rooms tables
                    // (Assuming you used the SQL schema from our very first step)
                    $sql = "SELECT id, booking_ref, guest_name, email, room_type,
                        check_in, check_out, nights, total_cost
                            FROM bookings
                            ORDER BY id DESC";

                    $result = $conn->query($sql);

                    // 5. Display the results in a Bootstrap Table
                    if ($result && $result->num_rows > 0) {
                        echo "<div class='table-responsive'>";
                        echo "<table class='table table-hover table-bordered align-middle'>";
                        echo "<thead class='table-light'>
                            <tr>
                                <th>Reference</th>
                                <th>Guest Name</th>
                                <th>Email</th>
                                <th>Room Type</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Nights</th>
                                <th>Total Cost</th>
                            </tr>
                          </thead>";
                        echo "<tbody>";

                        // Loop through each row in the database
                        // Loop through each row in the database
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>#" . htmlspecialchars($row["booking_ref"]) . "</td>";
                            echo "<td class='fw-bold'>" . htmlspecialchars($row["guest_name"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["email"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["room_type"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["check_in"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["check_out"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["nights"]) . " night(s)</td>";
                            echo "<td>₱" . number_format($row["total_cost"], 2) . "</td>";
                            echo "</tr>";
                        }
                        echo "</tbody></table></div>";
                    } else {
                        echo "<div class='alert alert-info'>No bookings found in the database.</div>";
                    }

                    // 6. Close the connection
                    $conn->close();
                }
                ?>
            </div>
        </div>
    </div>

</body>

</html>