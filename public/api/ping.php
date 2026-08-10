<?php
// Temporärer Diagnose-Endpunkt ohne jede Abhängigkeit — testet ausschließlich,
// ob eine frisch hinzugefügte .htaccess-Rewrite-Regel überhaupt greift.
// Wird nach der Fehlersuche wieder entfernt.
header('Content-Type: application/json');
echo json_encode(['pong' => true]);
