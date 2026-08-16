<?php
include('db.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$class_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($class_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$user_role = $_SESSION['role'];

$class_query = "SELECT classrooms.*, users.name AS teacher_name 
                FROM classrooms 
                JOIN users ON classrooms.teacher_id = users.user_id 
                WHERE classrooms.class_id = $class_id";
$class_res = $conn->query($class_query);

if (!$class_res || $class_res->num_rows == 0) {
    die("Classroom not found.");
}

$classroom = $class_res->fetch_assoc();

$is_owner = ($user_role == 'teacher' && $classroom['teacher_id'] == $user_id);
$is_enrolled = false;

if (!$is_owner && $user_role == 'student') {
    $check_query = "SELECT * FROM enrollments 
                    WHERE student_id = $user_id AND class_id = $class_id";

    $check_res = $conn->query($check_query);

    if ($check_res && $check_res->num_rows > 0) {
        $is_enrolled = true;
    }
}

$error_msg = "";
$success_msg = "";

if (!$is_owner && !$is_enrolled && isset($_POST['submit_password'])) {
    if ($_POST['typed_password'] === $classroom['class_password']) {
        $now = date('Y-m-d H:i:s');

        $enroll_sql = "INSERT INTO enrollments (student_id, class_id, date_joined) 
                       VALUES ($user_id, $class_id, '$now')";

        if ($conn->query($enroll_sql)) {
            $is_enrolled = true;
            $success_msg = "Password verified. You are now enrolled.";
        } else {
            $error_msg = "Enrollment failed. You may already be enrolled.";
        }
    } else {
        $error_msg = "Incorrect Password! Access Denied.";
    }
}

if ($is_owner && isset($_POST['manual_add_student'])) {
    $student_email = $conn->real_escape_string(trim($_POST['student_email']));

    $user_check = $conn->query("SELECT user_id, role FROM users WHERE email = '$student_email'");

    if ($user_check && $user_check->num_rows > 0) {
        $found_user = $user_check->fetch_assoc();

        if ($found_user['role'] !== 'student') {
            $error_msg = "The specified user is not a student.";
        } else {
            $target_student_id = intval($found_user['user_id']);

            $existing_check = $conn->query("SELECT * FROM enrollments WHERE student_id = $target_student_id AND class_id = $class_id");

            if ($existing_check && $existing_check->num_rows > 0) {
                $error_msg = "Student is already enrolled in this class.";
            } else {
                $now = date('Y-m-d H:i:s');
                $add_sql = "INSERT INTO enrollments (student_id, class_id, date_joined) VALUES ($target_student_id, $class_id, '$now')";

                if ($conn->query($add_sql)) {
                    $success_msg = "Student successfully added to the class.";
                } else {
                    $error_msg = "Failed to add student. Please try again.";
                }
            }
        }
    } else {
        $error_msg = "No user found with that email address.";
    }
}

$access_granted = ($is_owner || $is_enrolled);

if ($is_owner && isset($_POST['remove_student'])) {
    $enrollment_id = intval($_POST['enrollment_id']);

    $conn->query("DELETE FROM enrollments 
                  WHERE enrollment_id = $enrollment_id 
                  AND class_id = $class_id");

    header("Location: classroom.php?id=" . $class_id);
    exit();
}

if (!$is_owner && $user_role == 'student' && $is_enrolled && isset($_POST['leave_class'])) {
    $conn->query("DELETE FROM enrollments 
                  WHERE student_id = $user_id 
                  AND class_id = $class_id");

    header("Location: dashboard.php?msg=left_class");
    exit();
}

if ($is_owner && isset($_POST['post_announcement'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $content = $conn->real_escape_string($_POST['content']);
    $now = date('Y-m-d H:i:s');

    $conn->query("INSERT INTO announcements (class_id, title, content, date_posted) 
                  VALUES ($class_id, '$title', '$content', '$now')");

    header("Location: classroom.php?id=" . $class_id);
    exit();
}

if ($is_owner && isset($_POST['update_announcement'])) {
    $post_id = intval($_POST['post_id']);
    $title = $conn->real_escape_string($_POST['title']);
    $content = $conn->real_escape_string($_POST['content']);

    $conn->query("UPDATE announcements 
                  SET title = '$title', content = '$content' 
                  WHERE post_id = $post_id 
                  AND class_id = $class_id");

    header("Location: classroom.php?id=" . $class_id);
    exit();
}

if ($is_owner && isset($_GET['delete_post'])) {
    $post_id = intval($_GET['delete_post']);

    $conn->query("DELETE FROM announcements 
                  WHERE post_id = $post_id 
                  AND class_id = $class_id");

    header("Location: classroom.php?id=" . $class_id);
    exit();
}

if ($is_owner && isset($_POST['delete_class'])) {
    $conn->query("DELETE FROM classrooms WHERE class_id = $class_id");

    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($classroom['class_name']); ?> - Stream</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f9;
            margin: 0;
            padding: 20px;
        }

        .nav {
            margin-bottom: 20px;
        }

        .nav a {
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
        }

        .box {
            background: white;
            padding: 20px;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            max-width: 700px;
            margin: 20px auto;
        }

        input[type="text"], input[type="email"], textarea {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 0.85em;
        }

        .post-card {
            background: white;
            padding: 20px;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            max-width: 700px;
            margin: 15px auto;
            position: relative;
        }

        .post-date {
            font-size: 0.8em;
            color: #888;
            margin-bottom: 10px;
        }

        .delete-link {
            position: absolute;
            top: 20px;
            right: 20px;
            color: #dc3545;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.9em;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        .alert {
            padding: 10px;
            border-radius: 4px;
            margin: 10px auto;
            max-width: 700px;
            text-align: center;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>

<div class="nav">
    <a href="dashboard.php">← Back to Dashboard</a>
</div>

<?php if (!$access_granted): ?>

    <div class="box" style="text-align: center;">
        <h2>Lockbox: Enter <?php echo htmlspecialchars($classroom['class_name']); ?></h2>

        <?php if (!empty($error_msg)) { ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php } ?>

        <form action="classroom.php?id=<?php echo $class_id; ?>" method="POST">
            <input type="text" name="typed_password" placeholder="Enter Class Password" required>
            <button type="submit" name="submit_password" class="btn-primary">Verify & Enroll</button>
        </form>
    </div>

<?php else: ?>

    <?php if (!empty($error_msg)) { ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php } ?>

    <?php if (!empty($success_msg)) { ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_msg); ?>
        </div>
    <?php } ?>

    <div class="box" style="background: #007bff; color: white; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="margin: 0;"><?php echo htmlspecialchars($classroom['class_name']); ?></h1>
            <p style="margin: 5px 0 0 0; font-weight: bold; opacity: 0.9;"><?php echo htmlspecialchars($classroom['teacher_name']); ?></p>
        </div>

        <?php if ($is_owner): ?>
            <form action="classroom.php?id=<?php echo $class_id; ?>" method="POST" onsubmit="return confirm('Delete this classroom?');">
                <button type="submit" name="delete_class" class="btn-danger">Delete Class</button>
            </form>

        <?php elseif ($user_role == 'student' && $is_enrolled): ?>
            <form action="classroom.php?id=<?php echo $class_id; ?>" method="POST" onsubmit="return confirm('Leave this classroom?');">
                <button type="submit" name="leave_class" class="btn-danger">Leave Class</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($is_owner): ?>

        <div class="box" style="border-left: 4px solid #28a745;">
            <h3>Enrolled Students Roster</h3>

            <form action="classroom.php?id=<?php echo $class_id; ?>" method="POST" style="display: flex; gap: 10px; margin-bottom: 15px;">
                <input type="email" name="student_email" placeholder="Student Email Address" required style="margin: 0;">
                <button type="submit" name="manual_add_student" class="btn-primary" style="white-space: nowrap;">Add Student</button>
            </form>

            <?php
            $roster_query = "SELECT enrollments.enrollment_id, users.name, users.email 
                             FROM enrollments
                             JOIN users ON enrollments.student_id = users.user_id
                             WHERE enrollments.class_id = $class_id
                             ORDER BY users.name ASC";

            $roster_result = $conn->query($roster_query);

            if ($roster_result && $roster_result->num_rows > 0) {
                echo "<table>";
                echo "<tr>";
                echo "<th>Name</th>";
                echo "<th>Email</th>";
                echo "<th>Action</th>";
                echo "</tr>";

                while ($student = $roster_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($student['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($student['email']) . "</td>";
                    echo "<td>";
                    echo "<form method='POST' action='classroom.php?id=$class_id' onsubmit=\"return confirm('Remove this student from the class?');\">";
                    echo "<input type='hidden' name='enrollment_id' value='" . intval($student['enrollment_id']) . "'>";
                    echo "<button type='submit' name='remove_student' class='btn-danger btn-small'>Remove</button>";
                    echo "</form>";
                    echo "</td>";
                    echo "</tr>";
                }

                echo "</table>";
            } else {
                echo "<p style='color: #777; font-style: italic;'>No students have enrolled in this class yet.</p>";
            }
            ?>
        </div>

        <div class="box">
            <h3>Make an Announcement</h3>

            <form action="classroom.php?id=<?php echo $class_id; ?>" method="POST">
                <input type="text" name="title" placeholder="Topic Heading" required>
                <textarea name="content" rows="4" placeholder="Type your notice here..." required></textarea>
                <button type="submit" name="post_announcement" class="btn-primary">Publish Post</button>
            </form>
        </div>

    <?php endif; ?>

    <h3 style="text-align: center; color: #555;">Class Notices</h3>

    <?php
    $posts = $conn->query("SELECT * FROM announcements 
                           WHERE class_id = $class_id 
                           ORDER BY date_posted DESC");

    if ($posts && $posts->num_rows > 0) {
        while ($post = $posts->fetch_assoc()) {
            echo "<div class='post-card'>";

            echo "<h2>" . htmlspecialchars($post['title']) . "</h2>";
            echo "<div class='post-date'>Posted on: " . htmlspecialchars($post['date_posted']) . "</div>";
            echo "<p style='white-space: pre-line; color:#333;'>" . htmlspecialchars($post['content']) . "</p>";

            if ($is_owner) {

                echo "<a class='delete-link' 
                          href='classroom.php?id=$class_id&delete_post=" . intval($post['post_id']) . "' 
                          onclick=\"return confirm('Delete this notice?');\">
                          ✕ Delete
                      </a>";

                echo "<details style='margin-top: 15px; cursor: pointer; color: #007bff; font-size: 0.9em;'>";
                echo "<summary>📝 Edit Notice</summary>";

                echo "<form action='classroom.php?id=$class_id' method='POST' style='margin-top: 10px;'>";
                echo "<input type='hidden' name='post_id' value='" . intval($post['post_id']) . "'>";
                echo "<input type='text' name='title' value='" . htmlspecialchars($post['title'], ENT_QUOTES) . "' required>";
                echo "<textarea name='content' rows='3' required>" . htmlspecialchars($post['content']) . "</textarea>";
                echo "<button type='submit' name='update_announcement' class='btn-primary' style='padding: 5px 10px; font-size: 0.85em; background: #ffc107; color: black;'>Save Changes</button>";
                echo "</form>";

                echo "</details>";
            }

            echo "</div>";
        }
    } else {
        echo "<p style='text-align: center; color: #777;'>No announcements posted yet.</p>";
    }
    ?>

<?php endif; ?>

</body>
</html>