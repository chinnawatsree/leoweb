<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$host = "localhost";
$user = "root";
$pass = "";
$db = "leonics-testdb";
$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8");

// ดึงรายการภูมิภาคทั้งหมด
$regionFilter = $_GET['region'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$query = "SELECT * FROM customer WHERE 1";
if ($regionFilter !== '') {
  $query .= " AND region = '" . $conn->real_escape_string($regionFilter) . "'";
}
if ($statusFilter !== '') {
  $query .= " AND status = " . ($statusFilter === 'ใช้งาน' ? "0x0011000000" : "0x0000000000");
}

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>ตาราง UPS Monitoring</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">

  <div class="bg-white p-6 rounded shadow">
    <h1 class="text-xl font-bold mb-4">🔌 UPS Monitoring</h1>

    <!-- Filter -->
    <form method="GET" class="flex flex-wrap gap-3 mb-4">
      <!-- Region Buttons -->
      <?php
      $regions = ['North1', 'North2', 'Central', 'East', 'West', 'South', 'Bangkok'];
      foreach ($regions as $r) {
        echo "<button type='submit' name='region' value='$r' class='px-3 py-1 rounded bg-blue-500 text-white hover:bg-blue-600'>$r</button>";
      }
      ?>
      <button type="submit" name="region" value="" class="px-3 py-1 rounded bg-gray-400 text-white hover:bg-gray-500">ทั้งหมด</button>

      <!-- Status Dropdown -->
      <select name="status" onchange="this.form.submit()" class="px-3 py-1 border rounded">
        <option value="">-- สถานะทั้งหมด --</option>
        <option value="ใช้งาน" <?= $statusFilter === 'ใช้งาน' ? 'selected' : '' ?>>ใช้งาน</option>
        <option value="ไม่ใช้งาน" <?= $statusFilter === 'ไม่ใช้งาน' ? 'selected' : '' ?>>ไม่ใช้งาน</option>
      </select>
    </form>

    <!-- Table -->
    <div class="overflow-auto">
      <table class="min-w-full table-auto text-sm border border-gray-300">
        <thead class="bg-gray-100 text-gray-700">
          <tr>
            <th class="px-2 py-1 border">Region</th>
            <th class="px-2 py-1 border">Province</th>
            <th class="px-2 py-1 border">PEA</th>
            <th class="px-2 py-1 border">UPS ID</th>
            <th class="px-2 py-1 border">Last Signal</th>
            <th class="px-2 py-1 border">Event</th>
            <th class="px-2 py-1 border">Input (V)</th>
            <th class="px-2 py-1 border">Output (V)</th>
            <th class="px-2 py-1 border">Battery T (°C)</th>
            <th class="px-2 py-1 border">Battery V</th>
            <th class="px-2 py-1 border">UPS Qty</th>
            <th class="px-2 py-1 border">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $result->fetch_assoc()): ?>
          <tr class="hover:bg-gray-50">
            <td class="border px-2 py-1"><?= $row['region'] ?></td>
            <td class="border px-2 py-1"><?= $row['provinces'] ?></td>
            <td class="border px-2 py-1"><?= $row['pea'] ?></td>
            <td class="border px-2 py-1"><?= $row['upsID'] ?></td>
            <td class="border px-2 py-1"><?= $row['lastSignal'] ?></td>
            <td class="border px-2 py-1"><?= $row['lastEvent'] ?></td>
            <td class="border px-2 py-1"><?= $row['inputV'] ?></td>
            <td class="border px-2 py-1"><?= $row['outputV'] ?></td>
            <td class="border px-2 py-1"><?= $row['batteryT'] ?></td>
            <td class="border px-2 py-1"><?= $row['batteryV'] ?></td>
            <td class="border px-2 py-1"><?= $row['ups'] ?></td>
            <td class="border px-2 py-1 text-center">
              <?= $row['status'] === "\x00\x00\x00\x00\x00" ? '<span class="text-red-600">ไม่ใช้งาน</span>' : '<span class="text-green-600">ใช้งาน</span>' ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
