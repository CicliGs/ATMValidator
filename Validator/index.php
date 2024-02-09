<?php
// Функция для выполнения HTTP-запросов
function makeRequest($url) {
    return file_get_contents($url);
}

// Функция для получения информации о банкоматах в заданной области
function getATMInfo($area) {
    $apiUrl = "https://belarusbank.by/api/atm?area=" . urlencode($area);
    $jsonData = makeRequest($apiUrl);

    // Обработка JSON-данных
    $atmData = json_decode($jsonData, true);

    // Возвращаем данные о банкоматах
    return $atmData;
}

// Список областей в Беларуси
$areas = ["Брестская область", "Витебская область", "Гомельская область", "Гродненская область", "Минская область", "Могилевская область"];

// Массив для хранения маркеров
$markers = [];
$usedAddresses = [];

foreach ($areas as $area) {
    $atmData = getATMInfo($area);

    foreach ($atmData as $atm) {
        $address = $atm['address'] . ' ' . $atm['house'];
        $uniqueId = md5($address); // Генерируем уникальный идентификатор для адреса

        // Проверяем, был ли уже использован адрес
        if (!in_array($uniqueId, $usedAddresses)) {
            // Определение цвета чекбоксов в зависимости от условий
            $currencyColor = ($atm['currency'] == 'BYN') ? 'green' : (($atm['currency'] == 'USD') ? 'blue' : 'yellow');
            $twentyFourSevenColor = ($atm['work_time_full'] == 'Круглосуточно') ? 'green' : 'red';
            $errorColor = ($atm['ATM_error'] == 'нет') ? 'green' : 'red';
            $cashInColor = ($atm['cash_in'] == 'да') ? 'green' : 'red';

            $markers[] = [
                'lat' => $atm['gps_x'],
                'lng' => $atm['gps_y'],
                'popup' => '<b>' . $atm['install_place_full'] . '</b><br>Улица: ' . $atm['address'] . '<br>Номер банкомата: ' . $atm['install_place'] .
                            '<br><span style="color: ' . $currencyColor . ';">Выдаваемая валюта: ' . $atm['currency'] . '</span>' .
                            '<br><span style="color: ' . $twentyFourSevenColor . ';">Работает 24/7: ' . $atm['work_time_full'] . '</span>' .
                            '<br><span style="color: ' . $errorColor . ';">Исправность банкомата: ' . $atm['ATM_error'] . '</span>' .
                            '<br><span style="color: ' . $cashInColor . ';">Наличие купюроприемника: ' . $atm['cash_in'] . '</span>'
            ];

            // Добавляем уникальный идентификатор адреса в список использованных
            $usedAddresses[] = $uniqueId;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank ATMs Map</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        #map {
            height: 500px;
        }

        .checkbox-group {
            margin-bottom: 10px;
        }

        .checkbox-group label {
            margin-right: 15px;
        }
    </style>
</head>
<body>
    <div id="map"></div>

    <!-- Чекбоксы для фильтрации -->
    <div class="checkbox-group">
        <label><input type="checkbox" id="currency-checkbox"> Выдаваемая валюта BYN</label>
        <label><input type="checkbox" id="twenty-four-seven-checkbox"> Работает 24/7</label>
        <label><input type="checkbox" id="error-checkbox"> Неисправность банкомата</label>
        <label><input type="checkbox" id="cash-in-checkbox"> Наличие купюроприемника</label>
    </div>

    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />

    <script>
        function initMap() {
            var map = L.map('map').setView([53.9045, 27.5615], 6); // Координаты центра карты (Беларусь) и масштаб

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            var markers = <?php echo json_encode($markers); ?>;

            // Создаем кластер маркеров
            var markersCluster = L.markerClusterGroup();

            for (var i = 0; i < markers.length; i++) {
                var marker = L.marker([markers[i].lat, markers[i].lng]);
                marker.bindPopup(markers[i].popup);
                markersCluster.addLayer(marker);
            }

            map.addLayer(markersCluster);

            // Функция для фильтрации маркеров по чекбоксам
            function filterMarkers() {
                var currencyChecked = document.getElementById('currency-checkbox').checked;
                var twentyFourSevenChecked = document.getElementById('twenty-four-seven-checkbox').checked;
                var errorChecked = document.getElementById('error-checkbox').checked;
                var cashInChecked = document.getElementById('cash-in-checkbox').checked;

                markersCluster.clearLayers(); // Очищаем кластер

                for (var i = 0; i < markers.length; i++) {
                    var marker = L.marker([markers[i].lat, markers[i].lng]);

                    // Проверяем соответствие условиям фильтрации
                    if (
                        (currencyChecked && markers[i].popup.includes('Выдаваемая валюта: BYN')) ||
                        (twentyFourSevenChecked && markers[i].popup.includes('Работает 24/7: Круглосуточно')) ||
                        (errorChecked && markers[i].popup.includes('Исправность банкомата: нет')) ||
                        (cashInChecked && markers[i].popup.includes('Наличие купюроприемника: да'))
                    ) {
                        markersCluster.addLayer(marker);
                    }
                }
            }

            // Добавляем обработчики событий для чекбоксов
            document.getElementById('currency-checkbox').addEventListener('change', filterMarkers);
            document.getElementById('twenty-four-seven-checkbox').addEventListener('change', filterMarkers);
            document.getElementById('error-checkbox').addEventListener('change', filterMarkers);
            document.getElementById('cash-in-checkbox').addEventListener('change', filterMarkers);
        }
        initMap();
    </script>
</body>
</html>
