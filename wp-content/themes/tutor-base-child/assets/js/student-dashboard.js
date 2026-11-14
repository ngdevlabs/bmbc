(() => {
    const dashboard = document.querySelector('#bmb-student-dashboard');
    const filters = dashboard ? [...dashboard.querySelectorAll('[data-bmb-filter]')] : [];
    let selectedFilter = 'all';

    function filterClasses() {
        let visible = 0;
        dashboard?.querySelectorAll('[data-bmb-module]').forEach(module => {
            let count = 0;
            module.querySelectorAll('[data-bmb-class]').forEach(row => {
                row.hidden = selectedFilter === 'completed' && row.dataset.complete !== 'true';
                if (!row.hidden) count++;
            });
            module.hidden = count === 0;
            if (selectedFilter === 'completed' && count) module.open = true;
            visible += count;
        });
        const empty = dashboard?.querySelector('[data-bmb-no-completed]');
        if (empty) empty.hidden = selectedFilter !== 'completed' || visible > 0;
    }
    filters.forEach(button => button.addEventListener('click', () => {
        selectedFilter = button.dataset.bmbFilter;
        filters.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
        filterClasses();
    }));

    const boundVideos = new WeakSet();
    function bindVideo(video) {
        if (boundVideos.has(video)) return;
        const lessonId = Number(video.dataset.bmbVideo || bmbStudent.lessonId);
        if (!lessonId) return;
        boundVideos.add(video);
        let token = '';
        let starting = null;
        let saving = false;
        let saveAgain = false;
        let status = dashboard?.querySelector('[data-bmb-save-status]');
        if (!status) {
            status = document.createElement('p');
            status.className = 'bmb-video-save-status';
            status.setAttribute('role', 'status');
            status.setAttribute('aria-live', 'polite');
            status.style.cssText = 'padding:12px 16px;font-size:13px;line-height:1.5';
            (video.closest('.tutor-video-player') || video.parentElement).appendChild(status);
        }
        async function request(mode, ranges = []) {
            const body = new URLSearchParams({action: 'bmb_student_video', nonce: bmbStudent.nonce, lesson_id: lessonId, mode, token, ranges: JSON.stringify(ranges)});
            const response = await fetch(bmbStudent.ajaxUrl, {method: 'POST', body, credentials: 'same-origin', keepalive: true});
            const result = await response.json().catch(() => null);
            if (!response.ok || !result?.success) throw new Error(result?.data?.message || 'Progress could not be saved. Please reopen the class and try again.');
            return result.data;
        }
        async function start() {
            if (token) return;
            if (!starting) {
                starting = request('start').then(data => {token = data.token;}).finally(() => {starting = null;});
            }
            await starting;
        }
        function updateProgress(data) {
            if (!dashboard) {
                status.textContent = data.complete ? 'Class complete: full video watched and quiz passed.' : (data.video_complete ? 'Full video watched. Pass the class quiz to complete this class.' : `${data.percent}% watched. Progress saved.`);
                return;
            }
            dashboard.querySelector('[data-bmb-watch-label]').textContent = `${data.percent}% watched`;
            if (data.video_complete) dashboard.querySelector('[data-bmb-video-check]').textContent = '✓';
            const row = dashboard.querySelector(`[data-bmb-class="${lessonId}"]`);
            if (row) {
                const detail = row.querySelector('[data-bmb-class-detail]');
                detail.textContent = detail.textContent.replace(/^\d+% watched/, `${data.percent}% watched`);
                if (data.complete && row.dataset.complete !== 'true') {
                    detail.textContent = `${data.percent}% watched · Quiz passed`;
                    row.dataset.complete = 'true';
                    row.querySelector('[data-bmb-class-status]').textContent = 'Completed';
                    const indicator = row.querySelector('.bmb-class-indicator');
                    indicator.textContent = '✓'; indicator.classList.add('is-complete');
                    const count = dashboard.querySelector('[data-bmb-total-completed]');
                    count.textContent = String(Number(count.textContent) + 1);
                    const progress = dashboard.querySelector('[data-bmb-total-progress]');
                    progress.value = Number(count.textContent);
                    dashboard.querySelector('[data-bmb-total-percent]').textContent = `${Math.floor(progress.value / progress.max * 100)}%`;
                    const moduleCount = row.closest('[data-bmb-module]').querySelector('[data-bmb-module-count]');
                    moduleCount.textContent = String(Number(moduleCount.textContent) + 1);
                    dashboard.querySelector('[data-bmb-completion-label]').textContent = 'Completed — review this class anytime.';
                    filterClasses();
                } else if (!data.complete && data.percent > 0) {
                    row.querySelector('[data-bmb-class-status]').textContent = 'In progress';
                }
            }
            const nextLink = dashboard.querySelector('[data-bmb-continue]');
            if (data.complete && nextLink && Object.hasOwn(data, 'next_url')) {
                nextLink.href = data.next_url || '#bmb-syllabus';
                nextLink.textContent = data.next_url ? 'Continue Learning →' : 'Review your completed course →';
            }
            status.textContent = data.complete ? 'Class complete! Your progress is saved.' : (data.video_complete ? 'Full video watched. Pass the class quiz to complete this class.' : `${data.percent}% watched. Progress saved automatically.`);
        }
        async function save() {
            if (saving) {saveAgain = true; return;}
            if (!video.played.length) return;
            saving = true;
            try {
                await start();
                const ranges = [];
                for (let i = 0; i < video.played.length; i++) ranges.push([video.played.start(i), video.played.end(i)]);
                updateProgress(await request('save', ranges));
            } catch (error) {
                status.textContent = error.message;
            } finally {
                saving = false;
                if (saveAgain) {saveAgain = false; save();}
            }
        }
        video.addEventListener('play', () => start().catch(error => {status.textContent = error.message;}));
        video.addEventListener('ratechange', () => {if (video.playbackRate > 2) video.playbackRate = 2;});
        video.addEventListener('pause', save);
        video.addEventListener('ended', save);
        video.addEventListener('error', () => {status.textContent = 'This video could not be loaded. Please contact us if the problem continues.';});
        const interval = setInterval(() => {if (!video.isConnected) {clearInterval(interval); return;} if (!video.paused) save();}, 5000);
        window.addEventListener('pagehide', save);
        document.addEventListener('visibilitychange', () => {if (document.hidden) save();});
    }
    function scan() {document.querySelectorAll('video[data-bmb-video], video.tutorPlayer').forEach(bindVideo);}
    scan();
    // Tutor may replace the lesson player while navigating inside its learning area.
    new MutationObserver(scan).observe(document.body, {childList: true, subtree: true});
})();
