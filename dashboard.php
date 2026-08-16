<?php
include('db.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$user_name = $_SESSION['name'];
$user_role = $_SESSION['role'];

$msg = "";

if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'left_class') {
        $msg = "You have left the classroom.";
    }
}

if ($user_role == 'teacher' && isset($_POST['create_class'])) {
    $class_name = $conn->real_escape_string(trim($_POST['class_name']));
    $class_password = $conn->real_escape_string(trim($_POST['class_password']));

    $sql = "INSERT INTO classrooms (class_name, class_password, teacher_id) 
            VALUES ('$class_name', '$class_password', $user_id)";

    if ($conn->query($sql) === TRUE) {
        $msg = "Classroom created successfully!";
    } else {
        $msg = "Error creating classroom: " . $conn->error;
    }
}

$view = isset($_GET['view']) ? $_GET['view'] : 'my';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EWU Classroom - Dashboard</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f9;
            margin: 0;
            padding: 20px;
        }

        .header {
            background: #007bff;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 6px;
        }

        .header a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            background: #dc3545;
            padding: 8px 15px;
            border-radius: 4px;
        }

        .container {
            max-width: 900px;
            margin: 30px auto;
        }

        .form-box {
            background: white;
            padding: 20px;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        input, button {
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        input {
            width: calc(50% - 22px);
            margin-right: 10px;
        }

        button {
            background: #28a745;
            color: white;
            border: none;
            font-weight: bold;
            cursor: pointer;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-top: 4px solid #007bff;
            text-align: center;
        }

        .card a {
            display: inline-block;
            background: #007bff;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
            margin-top: 10px;
        }

        .alert {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .tabs {
            margin-bottom: 20px;
        }

        .tabs a {
            display: inline-block;
            padding: 10px 16px;
            background: white;
            border-radius: 4px;
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
            margin-right: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .tabs a.active {
            background: #007bff;
            color: white;
        }

        .badge {
            display: inline-block;
            margin-top: 8px;
            font-size: 0.85em;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .muted {
            color: #666;
            font-size: 0.88em;
        }
    </style>
</head>
<body>

<div class="header">
    <h2>
        Welcome, <?php echo htmlspecialchars($user_name); ?>
        (<?php echo ucfirst(htmlspecialchars($user_role)); ?>)
    </h2>
    <a href="logout.php">Log Out</a>
</div>

<div class="container">

    <?php if (!empty($msg)) { ?>
        <div class="alert"><?php echo htmlspecialchars($msg); ?></div>
    <?php } ?>

    <?php if ($user_role == 'teacher'): ?>

        <div class="form-box">
            <h3>Create a New Classroom</h3>

            <form action="dashboard.php" method="POST">
                <input type="text" name="class_name" placeholder="Class Name (e.g., CSE302)" required>
                <input type="text" name="class_password" placeholder="Set Entrance Password" required>
                <button type="submit" name="create_class">Create Class</button>
            </form>
        </div>

        <h3>Your Created Classrooms</h3>

        <div class="grid">
            <?php
            $query = "SELECT * FROM classrooms 
                      WHERE teacher_id = $user_id 
                      ORDER BY class_name ASC";

            $result = $conn->query($query);

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<div class='card'>";
                    echo "<h3>" . htmlspecialchars($row['class_name']) . "</h3>";
                    echo "<p class='muted'>Created by you</p>";
                    echo "<a href='classroom.php?id=" . intval($row['class_id']) . "'>Enter Class</a>";
                    echo "</div>";
                }
            } else {
                echo "<p>No classrooms found.</p>";
            }
            ?>
        </div>

    <?php else: ?>

        <h3>Student Classrooms</h3>

        <div class="tabs">
            <a href="dashboard.php?view=my" class="<?php echo ($view == 'my') ? 'active' : ''; ?>">
                My Classes
            </a>

            <a href="dashboard.php?view=public" class="<?php echo ($view == 'public') ? 'active' : ''; ?>">
                Public Feed
            </a>
        </div>

        <div class="grid">

            <?php
            if ($view == 'public') {

                $query = "SELECT classrooms.*,
                        (SELECT enrollment_id FROM enrollments
                         WHERE enrollments.class_id = classrooms.class_id
                         AND enrollments.student_id = $user_id
                         LIMIT 1) AS enrolled_id
                        FROM classrooms
                        ORDER BY classrooms.class_name ASC";

                $result = $conn->query($query);

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<div class='card'>";
                        echo "<h3>" . htmlspecialchars($row['class_name']) . "</h3>";

                        if (!empty($row['enrolled_id'])) {
                            echo "<div class='badge badge-success'>✔ Enrolled</div>";
                        } else {
                            echo "<div class='badge badge-warning'>🔒 Password Required</div>";
                        }

                        echo "<br>";
                        echo "<a href='classroom.php?id=" . intval($row['class_id']) . "'>Enter Class</a>";
                        echo "</div>";
                    }
                } else {
                    echo "<p>No classrooms found.</p>";
                }

            } else {

                $query = "SELECT classrooms.class_id,
                                 classrooms.class_name,
                                 classrooms.teacher_id,
                                 MAX(enrollments.date_joined) AS date_joined
                          FROM enrollments
                          JOIN classrooms ON enrollments.class_id = classrooms.class_id
                          WHERE enrollments.student_id = $user_id
                          GROUP BY classrooms.class_id, classrooms.class_name, classrooms.teacher_id
                          ORDER BY classrooms.class_name ASC";

                $result = $conn->query($query);

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<div class='card'>";
                        echo "<h3>" . htmlspecialchars($row['class_name']) . "</h3>";
                        echo "<div class='badge badge-success'>✔ Enrolled</div>";
                        echo "<p class='muted'>Joined: " . htmlspecialchars($row['date_joined']) . "</p>";
                        echo "<a href='classroom.php?id=" . intval($row['class_id']) . "'>Enter Class</a>";
                        echo "</div>";
                    }
                } else {
                    echo "<p>You have not enrolled in any classroom yet. Go to Public Feed to find classes.</p>";
                }
            }
            ?>

        </div>

    <?php endif; ?>

</div>

</body>
</html>