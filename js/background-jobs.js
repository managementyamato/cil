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
                const currentIds = new Set();
                for (const [id, job] of Object.entries(currentJobs)) {
                    if (job.dismissed) continue;
                    currentIds.add(id);

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

                // active から外れた (= 30秒以上前に完了 or 消えた) のに DOM に残ってる古い通知をクリーンアップ
                for (const id of Object.keys(knownJobs)) {
                    if (currentIds.has(id)) continue;
                    const el = document.getElementById('job-' + id);
                    if (el) {
                        el.style.animation = 'slideIn 0.3s ease-out reverse';
                        setTimeout(() => el.remove(), 300);
                    }
                    delete knownJobs[id];
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
    // 各ジョブ自身が `process_url` を持っていればそれを呼び出す (job_type ごとに違うエンドポイントを使える)。
    // 持っていなければ後方互換で loans-color.php を呼ぶ。
    function processJobs() {
        if (isProcessing) return;

        // 実行中ジョブを集め、process_url 別に重複排除
        const running = Object.values(knownJobs).filter(j => j && j.status === 'running');
        if (running.length === 0) return;

        const seen = new Set();
        const urls = [];
        for (const j of running) {
            const url = j.process_url || '/api/loans-color.php?action=process';
            if (!seen.has(url)) {
                seen.add(url);
                urls.push(url);
            }
        }

        isProcessing = true;
        // 各エンドポイントに対して 1 件ずつ処理依頼 (並列実行)
        Promise.allSettled(urls.map(url =>
            fetch(url).then(async r => {
                if (!r.ok) {
                    // 500等のサイレント失敗を防止 (consoleに表示しないと診断不能)
                    const text = await r.text().catch(() => '');
                    console.warn('[background-jobs] process failed', url, r.status, text.slice(0, 300));
                    return null;
                }
                return r.json();
            }).catch(err => {
                console.warn('[background-jobs] process error', url, err);
                return null;
            })
        )).then(results => {
            isProcessing = false;
            const anyProcessed = results.some(r =>
                r.status === 'fulfilled' && r.value && (r.value.processed || r.value.completed)
            );
            if (anyProcessed) {
                checkBackgroundJobs();
            }
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

    // 外部から即時チェックを呼べるよう公開 (ジョブ起動直後に呼ぶと floating notification が即表示される)
    window.checkBackgroundJobs = checkBackgroundJobs;

    // 初回チェック
    checkBackgroundJobs();
    // 定期チェック（アクティブなジョブがない場合は10秒おき）
    setInterval(checkBackgroundJobs, 10000);
})();
