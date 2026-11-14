(() => {
    const bar = document.querySelector('.bmb-learning-portal-bar');
    if (!bar) return;
    const body = document.body;
    let scheduled = false;
    function setSize(name, value) {
        const size = `${Math.round(value * 100) / 100}px`;
        if (body.style.getPropertyValue(name) !== size) body.style.setProperty(name, size);
    }
    function update() {
        scheduled = false;
        const admin = document.querySelector('#wpadminbar');
        const header = document.querySelector('.tutor-learning-header');
        const quizHeader = document.querySelector('.tutor-quiz-header');
        const quizFooter = document.querySelector('.tutor-quiz-submission .tutor-quiz-footer');
        setSize('--bmb-admin-bar-height', admin ? Math.max(0, admin.getBoundingClientRect().bottom) : 0);
        setSize('--bmb-portal-bar-height', bar.getBoundingClientRect().height);
        if (header) setSize('--bmb-course-header-height', header.getBoundingClientRect().height);
        if (quizHeader) setSize('--bmb-quiz-header-height', quizHeader.getBoundingClientRect().height);
        if (quizFooter) setSize('--bmb-quiz-footer-height', quizFooter.getBoundingClientRect().height);
    }
    function schedule() {
        if (!scheduled) {scheduled = true; requestAnimationFrame(update);}
    }
    const observer = new ResizeObserver(schedule);
    [bar, document.querySelector('#wpadminbar'), ...document.querySelectorAll('.tutor-learning-header, .tutor-quiz-header, .tutor-quiz-submission .tutor-quiz-footer')].filter(Boolean).forEach(el => observer.observe(el));
    window.addEventListener('resize', schedule);
    window.addEventListener('scroll', schedule, {passive: true});
    update();
})();
