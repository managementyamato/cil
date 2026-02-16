/**
 * バックグラウンドジョブ監視・処理
 */
(function() {
    'use strict';

    let pollingInterval = null;
    let processInterval = null;
    let knownJobs = {};
    let isProcessing = false;

    // ジョブ状態をチェック
    function checkBackgroundJobs() {
        fetch('/api/background-job.php?action=active')
            .then(r => r.json())
            .then(data => {
                if (!data.jobs) return;

                const container = document.getElementById('backgroundJobsContainer');
                if (!container) return;

                const currentJobs = data.jobs;

                // 新しいジョブまたは更新されたジョブを表示
                for (const [id, job] of Object.entries(currentJobs)) {
                    if (job.dismissed) continue;

                    let el = document.getElementById('job-' + id);
                    if (!el) {
                        el = createJobNotification(id, job);
                        container.appendChild(el);
                    } else {
                        updateJobNotification(el, job);
                    }
                    knownJobs[id] = job;
                }

                // 完了したジョブは一定時間後に自動で消す
                for (const [id, job] of Object.entries(currentJobs)) {
                    if ((job.status === 'completed' || job.status === 'failed') && !job.autoDismissScheduled) {
                        job.autoDismissScheduled = true;
                        setTimeout(() => {
                            const el = document.getElementById('job-' + id);
                            if (el) {
                                el.style.animation = 'slideIn 0.3s ease-out reverse';
                                setTimeout(() => el.remove(), 300);
                            }
                        }, 10000);
                    }
                }

                // 実行中のジョブがあれば処理を開始
                const hasRunning = Object.values(currentJobs).some(j => j.status === 'running');
                if (hasRunning) {
                    startProcessing();
                } else {
                    stopProcessing();
                }
            })
            .catch(() => {});
    }

    // ジョブの処理を進める
    function processJobs() {
        if (isProcessing) return;
        isProcessing = true;

        fetch('/api/loans-color.php?action=process')
            .then(r => r.json())
            .then(data => {
                isProcessing = false;
                if (data.processed) {
                    checkBackgroundJobs();
                }
            })
            .catch(() => {
                isProcessing = false;
            });
    }

    function startProcessing() {
        if (!processInterval) {
            // Google Sheets APIレート制限対策: 2秒間隔で処理
            processInterval = setInterval(processJobs, 2000);
            processJobs();
        }
        if (!pollingInterval) {
            pollingInterval = setInterval(checkBackgroundJobs, 3000);
        }
    }

    function stopProcessing() {
        if (processInterval) {
            clearInterval(processInterval);
            processInterval = null;
        }
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }

    function createJobNotification(id, job) {
        const el = document.createElement('div');
        el.id = 'job-' + id;
        el.className = 'background-job-notification ' + job.status;
        updateJobNotification(el, job);
        return el;
    }

    function updateJobNotification(el, job) {
        el.className = 'background-job-notification ' + job.status;

        let icon = '';
        if (job.status === 'running') {
            icon = '<div class="job-spinner"></div>';
        } else if (job.status === 'completed') {
            icon = '<svg class="job-icon" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';
        } else if (job.status === 'failed') {
            icon = '<svg class="job-icon" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
        }

        el.innerHTML = `
            ${icon}
            <div class="job-content">
                <div class="job-title">${escapeHtml(job.description || job.type)}</div>
                <div class="job-message">${escapeHtml(job.message || '')}</div>
            </div>
            <button class="job-dismiss" data-job-id="${escapeHtml(job.id)}" title="閉じる">✕</button>
        `;
        const dismissBtn = el.querySelector('.job-dismiss');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', function() {
                dismissJob(this.getAttribute('data-job-id'));
            });
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    window.dismissJob = function(jobId) {
        fetch('/api/background-job.php?action=dismiss&job_id=' + jobId)
            .then(() => {
                const el = document.getElementById('job-' + jobId);
                if (el) {
                    el.style.animation = 'slideIn 0.3s ease-out reverse';
                    setTimeout(() => el.remove(), 300);
                }
            });
    };

    // 初回チェック
    checkBackgroundJobs();
    // 定期チェック（アクティブなジョブがない場合は10秒おき）
    setInterval(checkBackgroundJobs, 10000);
})();
