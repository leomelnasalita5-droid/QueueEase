<?php
require_once 'includes/auth.php';
require_login();

$user = current_user();

if ($user['role'] === 'admin') {
    redirect('admin/dashboard.php');
}

$pageTitle = 'Student Dashboard';
$base = '';

$services = $pdo->query("SELECT * FROM services ORDER BY name")->fetchAll();

$stmt = $pdo->prepare("
    SELECT q.*, s.name AS service_name
    FROM queues q
    JOIN services s ON q.service_id = s.id
    WHERE q.user_id = ? AND q.queue_date = CURDATE()
      AND q.status IN ('Waiting', 'Serving')
    ORDER BY q.created_at DESC
");
$stmt->execute([$user['id']]);
$activeQueues = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<h1>Welcome, <?= clean($user['name']) ?>!</h1>

<h2>School Office Services</h2>

<div class="card">
    <?php foreach ($services as $service): ?>
        <?php
        $controlStmt = $pdo->prepare("
            SELECT is_open, daily_limit
            FROM queue_controls
            WHERE service_id = ? AND queue_date = CURDATE()
        ");
        $controlStmt->execute([$service['id']]);
        $control = $controlStmt->fetch();

        $officeOpen = $control ? (bool) $control['is_open'] : true;
        $limit = $control ? (int) $control['daily_limit'] : 100;

        $countStmt = $pdo->prepare("
            SELECT COUNT(*) FROM queues
            WHERE service_id = ? AND queue_date = CURDATE()
        ");
        $countStmt->execute([$service['id']]);
        $used = (int) $countStmt->fetchColumn();

        if ($used >= $limit) {
            $officeOpen = false;
            $pdo->prepare("
                UPDATE queue_controls SET is_open = 0
                WHERE service_id = ? AND queue_date = CURDATE()
            ")->execute([$service['id']]);
        }
        ?>

        <h3><?= clean($service['name']) ?></h3>
        <p><?= clean($service['description']) ?></p>
        <p>Tickets issued today: <?= $used ?> / <?= $limit ?></p>
        <p>Queue status: <strong><?= $officeOpen ? 'OPEN' : 'CLOSED' ?></strong></p>

        <?php if ($officeOpen): ?>
            <form action="take_queue.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= clean(csrf_token()) ?>">
                <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
                <button type="submit">Get Queue Number</button>
            </form>
        <?php else: ?>
            <button type="button" disabled>Queue Closed</button>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<h2>Your Active Queues</h2>

<div id="queue-alert" class="card" role="status" aria-live="polite" hidden></div>

<p>
    <button type="button" id="enable-sound">Enable Sound Alerts</button>
    <span id="sound-status">Sound is not enabled.</span>
</p>

<div id="active-queues">
    <?php if ($activeQueues): ?>
        <?php foreach ($activeQueues as $queue): ?>
            <div class="card" id="queue-card-<?= (int) $queue['id'] ?>"
                 data-queue-id="<?= (int) $queue['id'] ?>"
                 data-status="<?= clean($queue['status']) ?>">
                <h3><?= clean($queue['service_name']) ?></h3>
                <p>Queue Number: Q-<?= str_pad($queue['queue_number'], 3, '0', STR_PAD_LEFT) ?></p>
                <p>Status: <strong id="queue-status-<?= (int) $queue['id'] ?>"><?= clean($queue['status']) ?></strong></p>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p id="no-queues">You have no active queues today.</p>
    <?php endif; ?>
</div>

<script>
(() => {
    const alertBox = document.getElementById('queue-alert');
    const soundButton = document.getElementById('enable-sound');
    const soundStatus = document.getElementById('sound-status');

    let audioContext = null;
    let soundEnabled = false;

    const previousStatuses = new Map();

    document.querySelectorAll('[data-queue-id]').forEach(card => {
        previousStatuses.set(
            card.dataset.queueId,
            card.dataset.status
        );

        if (card.dataset.status === 'Serving') {
            alertBox.hidden = false;
            alertBox.textContent =
                'Your queue is currently being served. Please proceed to the office.';
        }
    });

    soundButton.addEventListener('click', async () => {
        try {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;

            if (!AudioContextClass) {
                soundStatus.textContent = 'Sound is not supported by this browser.';
                return;
            }

            audioContext = audioContext || new AudioContextClass();
            await audioContext.resume();

            soundEnabled = true;
            soundStatus.textContent = 'Sound alerts enabled.';
            soundButton.textContent = 'Sound Alerts Enabled';
        } catch (error) {
            soundStatus.textContent = 'Unable to enable sound.';
        }
    });

    function playBeep() {
        if (!soundEnabled || !audioContext) return;

        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();

        oscillator.connect(gain);
        gain.connect(audioContext.destination);

        oscillator.frequency.value = 880;
        gain.gain.value = 0.15;

        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.5);
    }

    async function checkQueues() {
        try {
            const response = await fetch('queue_status.php', {
                cache: 'no-store',
                credentials: 'same-origin'
            });

            if (!response.ok) return;

            const data = await response.json();

            data.queues.forEach(queue => {
                const id = String(queue.id);
                const card = document.getElementById('queue-card-' + id);
                const statusElement = document.getElementById('queue-status-' + id);

                if (!card || !statusElement) return;

                const oldStatus = previousStatuses.get(id);

                statusElement.textContent = queue.status;
                card.dataset.status = queue.status;

                if (queue.status === 'Serving' && oldStatus !== 'Serving') {
                    alertBox.hidden = false;
                    alertBox.textContent =
                        'YOUR TURN! Queue Q-' +
                        String(queue.queue_number).padStart(3, '0') +
                        ' at ' + queue.service_name +
                        ' is now being served. Please proceed to the office.';

                    playBeep();
                }

                previousStatuses.set(id, queue.status);
            });
        } catch (error) {
            // Retry automatically on the next polling cycle.
        }
    }

    setInterval(checkQueues, 3000);
    checkQueues();
})();
</script>

<?php require_once 'includes/footer.php'; ?>
