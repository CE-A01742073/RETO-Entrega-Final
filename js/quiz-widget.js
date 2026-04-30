const QuizWidget = (() => {
    let _lessonId, _courseId, _quiz, _startTime, _currentQ = 0, _answers = {}, _timerInterval;

    function buildShell() {
        const shell = document.createElement('div');
        shell.id = 'quiz-widget-wrap';
        shell.innerHTML = `
        <div id="quiz-widget">
            <div id="quiz-header">
                <div id="quiz-header-left">
                    <div id="quiz-icon">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm2 0a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1H7a1 1 0 110-2h1v-1a1 1 0 011-1zm-2 5a1 1 0 100 2h.01a1 1 0 100-2H7zm2 0a1 1 0 000 2h2a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                    </div>
                    <div>
                        <div id="quiz-title-label">Cargando quiz…</div>
                        <div id="quiz-meta"></div>
                    </div>
                </div>
                <div id="quiz-header-right">
                    <span id="quiz-timer" style="display:none"></span>
                    <span id="quiz-progress-label"></span>
                </div>
            </div>

            <div id="quiz-progress-bar-wrap"><div id="quiz-progress-bar"></div></div>

            <!-- Pantalla de inicio -->
            <div id="quiz-screen-start" class="quiz-screen">
                <div class="quiz-start-icon">🧠</div>
                <h3 id="qs-title"></h3>
                <p id="qs-desc"></p>
                <div id="qs-stats"></div>
                <button class="quiz-btn quiz-btn-primary" onclick="QuizWidget._start()">Comenzar Quiz</button>
            </div>

            <!-- Pantalla de pregunta -->
            <div id="quiz-screen-question" class="quiz-screen" style="display:none">
                <div id="qz-qnum"></div>
                <div id="qz-qtext"></div>
                <div id="qz-hint"></div>
                <div id="qz-options"></div>
                <div id="qz-nav">
                    <button class="quiz-btn quiz-btn-outline" onclick="QuizWidget._prev()">← Anterior</button>
                    <button class="quiz-btn quiz-btn-primary" id="qz-next-btn" onclick="QuizWidget._next()">Siguiente →</button>
                </div>
            </div>

            <!-- Pantalla de resultado -->
            <div id="quiz-screen-result" class="quiz-screen" style="display:none">
                <div id="qr-icon"></div>
                <div id="qr-score"></div>
                <div id="qr-label"></div>
                <div id="qr-review"></div>
                <div id="qr-actions"></div>
            </div>
        </div>`;
        return shell;
    }

    // ── Inyectar CSS ───────────────────────────────────────────────
    function injectStyles() {
        if (document.getElementById('quiz-widget-styles')) return;
        const s = document.createElement('style');
        s.id = 'quiz-widget-styles';
        s.textContent = `
        #quiz-widget-wrap{margin:2rem 0;}
        #quiz-widget{background:#fff;border-radius:16px;border:1.5px solid #E5F4FC;box-shadow:0 4px 24px rgba(0,73,118,0.07);overflow:hidden;font-family:'Open Sans',sans-serif;}
        #quiz-header{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;background:linear-gradient(135deg,#004976,#0077B6);gap:1rem;}
        #quiz-header-left{display:flex;align-items:center;gap:0.75rem;}
        #quiz-icon{width:36px;height:36px;background:rgba(255,255,255,0.15);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
        #quiz-icon svg{width:18px;height:18px;fill:white;}
        #quiz-title-label{font-family:'Nunito Sans',sans-serif;font-weight:800;font-size:0.95rem;color:white;}
        #quiz-meta{font-size:0.72rem;color:rgba(255,255,255,0.7);margin-top:0.1rem;}
        #quiz-header-right{display:flex;align-items:center;gap:0.75rem;flex-shrink:0;}
        #quiz-timer{font-family:'Nunito Sans',sans-serif;font-weight:800;font-size:0.85rem;color:white;background:rgba(255,255,255,0.15);border-radius:99px;padding:0.2rem 0.625rem;}
        #quiz-progress-label{font-size:0.75rem;color:rgba(255,255,255,0.8);}
        #quiz-progress-bar-wrap{height:4px;background:#E5F4FC;}
        #quiz-progress-bar{height:4px;background:#0099D8;transition:width 0.35s ease;width:0%;}
        .quiz-screen{padding:1.75rem 1.5rem;}

        /* Inicio */
        .quiz-start-icon{font-size:2.5rem;text-align:center;margin-bottom:0.75rem;}
        #qs-title{font-family:'Nunito Sans',sans-serif;font-size:1.2rem;font-weight:800;color:#004976;text-align:center;margin:0 0 0.5rem;}
        #qs-desc{color:#555;font-size:0.88rem;text-align:center;margin:0 0 1.25rem;line-height:1.6;}
        #qs-stats{display:flex;justify-content:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;}
        .qs-stat{background:#F0F8FF;border-radius:10px;padding:0.6rem 1rem;text-align:center;min-width:90px;}
        .qs-stat-val{font-family:'Nunito Sans',sans-serif;font-weight:800;font-size:1.1rem;color:#004976;}
        .qs-stat-lbl{font-size:0.7rem;color:#6B6B6B;margin-top:0.1rem;}

        /* Pregunta */
        #qz-qnum{font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#0099D8;margin-bottom:0.5rem;}
        #qz-qtext{font-family:'Nunito Sans',sans-serif;font-weight:700;font-size:1rem;color:#1A1A1A;line-height:1.5;margin-bottom:0.5rem;}
        #qz-hint{font-size:0.75rem;color:#8C8C8C;margin-bottom:1rem;}
        #qz-options{display:flex;flex-direction:column;gap:0.5rem;margin-bottom:1.25rem;}
        .qz-option{display:flex;align-items:center;gap:0.75rem;padding:0.8rem 1rem;border:1.5px solid #E8EEF5;border-radius:10px;cursor:pointer;transition:all 0.15s;font-size:0.88rem;color:#2D2D2D;}
        .qz-option:hover{border-color:#0099D8;background:#F0F9FF;}
        .qz-option.selected{border-color:#0099D8;background:#E5F4FC;color:#004976;font-weight:600;}
        .qz-option.correct{border-color:#10B981;background:#ECFDF5;color:#065F46;}
        .qz-option.wrong{border-color:#EF4444;background:#FEF2F2;color:#991B1B;}
        .qz-option.correct-missed{border-color:#10B981;background:#ECFDF5;opacity:0.7;}
        .qz-option-marker{width:20px;height:20px;border-radius:50%;border:2px solid #C4D0DC;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:800;}
        .qz-option.selected .qz-option-marker{background:#0099D8;border-color:#0099D8;color:white;}
        .qz-option.correct .qz-option-marker{background:#10B981;border-color:#10B981;color:white;}
        .qz-option.wrong .qz-option-marker{background:#EF4444;border-color:#EF4444;color:white;}
        .qz-checkbox .qz-option-marker{border-radius:4px;}
        #qz-nav{display:flex;justify-content:space-between;align-items:center;gap:0.75rem;}

        /* Resultado */
        #qr-icon{font-size:3.5rem;text-align:center;margin-bottom:0.75rem;}
        #qr-score{text-align:center;margin-bottom:0.5rem;}
        .qr-score-num{font-family:'Nunito Sans',sans-serif;font-weight:900;font-size:3rem;line-height:1;}
        .qr-score-pct{font-family:'Nunito Sans',sans-serif;font-weight:700;font-size:1.1rem;color:#6B6B6B;}
        #qr-label{text-align:center;font-family:'Nunito Sans',sans-serif;font-weight:700;font-size:1rem;margin-bottom:1.25rem;}
        #qr-review{display:flex;flex-direction:column;gap:0.75rem;margin-bottom:1.5rem;}
        .qr-review-item{border-radius:10px;border:1.5px solid #E8EEF5;overflow:hidden;}
        .qr-review-header{display:flex;align-items:flex-start;gap:0.625rem;padding:0.75rem 1rem;background:#F8FAFB;}
        .qr-review-icon{font-size:1rem;flex-shrink:0;margin-top:0.1rem;}
        .qr-review-qtext{font-size:0.83rem;font-weight:600;color:#1A1A1A;line-height:1.45;}
        .qr-review-opts{padding:0.5rem 1rem 0.75rem 2.5rem;display:flex;flex-direction:column;gap:0.3rem;}
        .qr-opt-row{font-size:0.78rem;padding:0.3rem 0.5rem;border-radius:6px;}
        .qr-opt-correct{background:#ECFDF5;color:#065F46;}
        .qr-opt-wrong{background:#FEF2F2;color:#991B1B;}
        #qr-actions{display:flex;gap:0.75rem;justify-content:center;flex-wrap:wrap;}

        /* Botones */
        .quiz-btn{padding:0.625rem 1.25rem;border-radius:8px;font-family:'Nunito Sans',sans-serif;font-weight:700;font-size:0.875rem;border:none;cursor:pointer;transition:all 0.15s;}
        .quiz-btn-primary{background:#004976;color:white;}
        .quiz-btn-primary:hover{background:#0077B6;}
        .quiz-btn-outline{background:transparent;color:#004976;border:1.5px solid #004976;}
        .quiz-btn-outline:hover{background:#F0F8FF;}
        .quiz-btn-retry{background:#0099D8;color:white;}
        .quiz-btn-retry:hover{background:#007CB0;}

        @media(max-width:600px){
            .quiz-screen{padding:1.25rem 1rem;}
            .qr-score-num{font-size:2.25rem;}
            #qz-nav{flex-direction:column;}
            .quiz-btn{width:100%;justify-content:center;}
        }
        `;
        document.head.appendChild(s);
    }

    async function init(lessonId, courseId, containerId) {
        _lessonId = lessonId;
        _courseId = courseId;

        injectStyles();
        const container = document.getElementById(containerId || 'quiz-container');
        if (!container) return;

        container.innerHTML = '';
        const shell = buildShell();
        container.appendChild(shell);

        try {
            const res = await fetch('/admin/manage_quiz.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action:'get', lesson_id: lessonId})
            });
            const data = await res.json();
            if (!data.success || !data.quiz) {
                container.innerHTML = '';
                return; 
            }
            _quiz = data.quiz;
            _renderStart();
        } catch (e) {
            console.error('QuizWidget error:', e);
        }
    }

    function _renderStart() {
        document.getElementById('quiz-title-label').textContent = _quiz.quiz_title;
        const attLeft = _quiz.max_attempts > 0
            ? `${_quiz.max_attempts} intentos máx.`
            : 'Intentos ilimitados';
        document.getElementById('quiz-meta').textContent = `${_quiz.questions.length} preguntas · ${attLeft}`;

        document.getElementById('qs-title').textContent = _quiz.quiz_title;
        document.getElementById('qs-desc').textContent = _quiz.is_required
            ? 'Debes aprobar este quiz para marcar la lección como completada.'
            : 'Pon a prueba lo que aprendiste en esta lección.';

        const statsEl = document.getElementById('qs-stats');
        const stats = [
            {val: _quiz.questions.length, lbl: 'Preguntas'},
            {val: _quiz.passing_score + '%', lbl: 'Para aprobar'},
            {val: _quiz.time_limit_min > 0 ? _quiz.time_limit_min + ' min' : '∞', lbl: 'Tiempo'},
        ];
        statsEl.innerHTML = stats.map(s => `
            <div class="qs-stat">
                <div class="qs-stat-val">${s.val}</div>
                <div class="qs-stat-lbl">${s.lbl}</div>
            </div>`).join('');

        _updateProgress(0);
    }

    function _start() {
        _currentQ = 0;
        _answers  = {};
        _startTime = Date.now();
        _showScreen('question');
        _renderQuestion();
        if (_quiz.time_limit_min > 0) _startTimer(_quiz.time_limit_min * 60);
    }

    function _startTimer(secs) {
        let remaining = secs;
        const el = document.getElementById('quiz-timer');
        el.style.display = 'inline';
        _timerInterval = setInterval(() => {
            remaining--;
            const m = String(Math.floor(remaining/60)).padStart(2,'0');
            const s = String(remaining%60).padStart(2,'0');
            el.textContent = `${m}:${s}`;
            if (remaining <= 0) { clearInterval(_timerInterval); _submitQuiz(); }
        }, 1000);
    }

    function _renderQuestion() {
        const q = _quiz.questions[_currentQ];
        const total = _quiz.questions.length;
        const isMultiple = q.question_type === 'multiple';

        document.getElementById('qz-qnum').textContent = `Pregunta ${_currentQ+1} de ${total}`;
        document.getElementById('qz-qtext').textContent = q.question_text;
        document.getElementById('qz-hint').textContent = isMultiple
            ? 'Selecciona todas las respuestas correctas.'
            : 'Selecciona una respuesta.';

        const chosen = _answers[q.question_id] || [];
        const letters = ['A','B','C','D','E'];

        const optsEl = document.getElementById('qz-options');
        optsEl.innerHTML = q.options.map((opt, oi) => {
            const sel = chosen.includes(opt.option_id) ? 'selected' : '';
            const cls = isMultiple ? 'qz-checkbox' : '';
            return `<div class="qz-option ${sel} ${cls}" data-oid="${opt.option_id}" onclick="QuizWidget._pick(${q.question_id}, ${opt.option_id}, '${q.question_type}', this)">
                <div class="qz-option-marker">${isMultiple ? '' : letters[oi]}</div>
                <span>${opt.option_text}</span>
            </div>`;
        }).join('');

        // Botón siguiente / enviar
        const nextBtn = document.getElementById('qz-next-btn');
        nextBtn.textContent = _currentQ < total-1 ? 'Siguiente →' : 'Enviar Quiz ✓';
        nextBtn.className   = _currentQ < total-1 ? 'quiz-btn quiz-btn-primary' : 'quiz-btn quiz-btn-retry';

        // Ocultar "Anterior" en pregunta 0
        document.querySelector('#qz-nav .quiz-btn-outline').style.visibility = _currentQ === 0 ? 'hidden' : 'visible';

        _updateProgress((_currentQ / total) * 100);
        document.getElementById('quiz-progress-label').textContent = `${_currentQ+1}/${total}`;
    }

    function _pick(questionId, optionId, qtype, el) {
        if (!_answers[questionId]) _answers[questionId] = [];
        const opts = document.querySelectorAll('#qz-options .qz-option');

        if (qtype === 'single' || qtype === 'truefalse') {
            _answers[questionId] = [optionId];
            opts.forEach(o => o.classList.remove('selected'));
            el.classList.add('selected');
        } else {
            // multiple
            const idx = _answers[questionId].indexOf(optionId);
            if (idx === -1) {
                _answers[questionId].push(optionId);
                el.classList.add('selected');
            } else {
                _answers[questionId].splice(idx, 1);
                el.classList.remove('selected');
            }
        }
    }

    function _prev() {
        if (_currentQ > 0) { _currentQ--; _renderQuestion(); }
    }

    function _next() {
        const q = _quiz.questions[_currentQ];
        const chosen = _answers[q.question_id] || [];
        if (chosen.length === 0) {
            document.getElementById('qz-hint').textContent = '⚠️ Debes seleccionar una respuesta para continuar.';
            document.getElementById('qz-hint').style.color = '#D97706';
            return;
        }
        if (_currentQ < _quiz.questions.length - 1) {
            _currentQ++;
            _renderQuestion();
        } else {
            _submitQuiz();
        }
    }

    async function _submitQuiz() {
        clearInterval(_timerInterval);
        const timeSpent = Math.round((Date.now() - _startTime) / 1000);
        const answersPayload = Object.entries(_answers).map(([qid, oids]) => ({
            question_id: parseInt(qid),
            option_ids: oids
        }));

        _showScreen('result');
        document.getElementById('qr-icon').textContent = '⏳';
        document.getElementById('qr-score').innerHTML = '<div class="qr-score-pct">Calculando resultados…</div>';
        document.getElementById('qr-label').textContent = '';
        document.getElementById('qr-review').innerHTML = '';
        document.getElementById('qr-actions').innerHTML = '';

        try {
            const res = await fetch('/process/submit_quiz.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({
                    quiz_id:       _quiz.quiz_id,
                    lesson_id:     _lessonId,
                    course_id:     _courseId,
                    answers:       answersPayload,
                    time_spent_sec: timeSpent
                })
            });
            const data = await res.json();
            if (data.success) _renderResult(data);
            else _renderError(data.error || 'Error al enviar el quiz.');
        } catch(e) {
            _renderError('Error de conexión. Intenta de nuevo.');
        }
    }

    function _renderResult(data) {
        const passed = data.passed;
        document.getElementById('qr-icon').textContent = passed ? '🏆' : '📚';

        const scoreColor = passed ? '#10B981' : '#EF4444';
        document.getElementById('qr-score').innerHTML = `
            <div class="qr-score-num" style="color:${scoreColor}">${data.score}%</div>
            <div class="qr-score-pct">${data.earned_points} / ${data.total_points} preguntas correctas</div>`;

        document.getElementById('qr-label').innerHTML = passed
            ? `<span style="color:#065F46">✅ ¡Aprobado! Superaste el ${data.passing_score}% requerido.</span>`
            : `<span style="color:#991B1B">❌ No aprobaste. Necesitas ${data.passing_score}%. Vuelve a repasar el contenido.</span>`;

        // Revisión por pregunta
        const reviewEl = document.getElementById('qr-review');
        reviewEl.innerHTML = data.questions.map(q => {
            const icon = q.is_correct ? '✅' : '❌';
            const opts = q.options.map(o => {
                let cls = '';
                const correct  = o.is_correct  == 1 || o.is_correct  === true;
                const chosen   = o.was_chosen  == 1 || o.was_chosen  === true;
                if (correct)          cls = 'qr-opt-correct';
                if (chosen && !correct) cls = 'qr-opt-wrong';
                if (!cls) return '';
                const mark = correct ? '✓' : '✗';
                return `<div class="qr-opt-row ${cls}">${mark} ${o.option_text}</div>`;
            }).filter(Boolean).join('');

            return `<div class="qr-review-item">
                <div class="qr-review-header">
                    <div class="qr-review-icon">${icon}</div>
                    <div class="qr-review-qtext">${q.question_text}</div>
                </div>
                ${opts ? `<div class="qr-review-opts">${opts}</div>` : ''}
            </div>`;
        }).join('');

        // Acciones
        const actEl = document.getElementById('qr-actions');
        let html = '';
        if (!passed && data.attempts_left > 0) {
            html += `<button class="quiz-btn quiz-btn-retry" onclick="QuizWidget._start()">🔄 Reintentar (${data.attempts_left} restantes)</button>`;
        }
        if (passed) {
            html += `<button class="quiz-btn quiz-btn-primary" onclick="document.getElementById('quiz-widget-wrap').style.display='none'">Continuar →</button>`;
            // Forzar actualización del botón de lección completada si existe
            const completeBtn = document.getElementById('complete-btn');
            if (completeBtn) {
                completeBtn.click();
            }
        }
        actEl.innerHTML = html;

        _updateProgress(100);
    }

    function _renderError(msg) {
        document.getElementById('qr-icon').textContent = '⚠️';
        document.getElementById('qr-score').innerHTML = `<div class="qr-score-pct" style="color:#D97706">${msg}</div>`;
        document.getElementById('qr-actions').innerHTML = `<button class="quiz-btn quiz-btn-outline" onclick="QuizWidget._start()">Reintentar</button>`;
    }

    function _showScreen(name) {
        ['start','question','result'].forEach(s => {
            const el = document.getElementById(`quiz-screen-${s}`);
            if (el) el.style.display = s === name ? 'block' : 'none';
        });
    }

    function _updateProgress(pct) {
        const bar = document.getElementById('quiz-progress-bar');
        if (bar) bar.style.width = pct + '%';
    }

    return { init, _start, _prev, _next, _pick };
})();