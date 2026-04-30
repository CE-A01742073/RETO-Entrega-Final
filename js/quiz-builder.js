const QuizBuilder = (() => {

    let _lessonId, _courseId, _lessonTitle = '';

    function _ensureModal() {
        if (document.getElementById('qb-modal')) return;
        const m = document.createElement('div');
        m.id = 'qb-modal';
        m.innerHTML = `
        <div id="qb-backdrop" onclick="QuizBuilder.close()"></div>
        <div id="qb-panel">
            <div id="qb-header">
                <div id="qb-header-left">
                    <div id="qb-header-icon">📝</div>
                    <div>
                        <div id="qb-header-title">Quiz de Lección</div>
                        <div id="qb-header-sub">Editor de preguntas</div>
                    </div>
                </div>
                <button id="qb-close-btn" onclick="QuizBuilder.close()">✕</button>
            </div>

            <div id="qb-body">

                <!-- Config -->
                <div class="qb-section">
                    <div class="qb-section-title">Configuración</div>
                    <div class="qb-config-grid">
                        <div class="qb-field">
                            <label>Título del quiz</label>
                            <input id="qb-cfg-title" type="text" placeholder="Quiz de la Lección" class="qb-input">
                        </div>
                        <div class="qb-field">
                            <label>% mínimo para aprobar</label>
                            <input id="qb-cfg-passing" type="number" min="10" max="100" value="70" class="qb-input">
                        </div>
                        <div class="qb-field">
                            <label>Intentos máximos <span style="color:#8C8C8C;font-weight:400">(0 = ilimitados)</span></label>
                            <input id="qb-cfg-attempts" type="number" min="0" max="10" value="3" class="qb-input">
                        </div>
                        <div class="qb-field">
                            <label>Tiempo límite (min) <span style="color:#8C8C8C;font-weight:400">(0 = sin límite)</span></label>
                            <input id="qb-cfg-time" type="number" min="0" max="120" value="0" class="qb-input">
                        </div>
                        <div class="qb-field qb-field-full">
                            <label class="qb-checkbox-label">
                                <input id="qb-cfg-required" type="checkbox">
                                <span>Requerido para marcar lección como completada</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- IA -->
                <div class="qb-section">
                    <div class="qb-ai-card">
                        <div class="qb-ai-header">
                            <span class="qb-ai-icon">✨</span>
                            <div>
                                <div class="qb-ai-title">Generar preguntas con Gemini</div>
                                <div class="qb-ai-sub">La IA crea preguntas basadas en el contenido de la lección</div>
                            </div>
                            <button class="qb-ai-toggle" id="qb-ai-toggle-btn" onclick="QuizBuilder._toggleAI()">Configurar ▾</button>
                        </div>
                        <div id="qb-ai-body" style="display:none;">
                            <div class="qb-ai-fields">
                                <div class="qb-field">
                                    <label>Número de preguntas</label>
                                    <select id="qb-ai-num" class="qb-input">
                                        <option value="3">3 preguntas</option>
                                        <option value="5" selected>5 preguntas</option>
                                        <option value="8">8 preguntas</option>
                                        <option value="10">10 preguntas</option>
                                        <option value="15">15 preguntas</option>
                                    </select>
                                </div>
                                <div class="qb-field">
                                    <label>Nivel de dificultad</label>
                                    <select id="qb-ai-level" class="qb-input">
                                        <option value="beginner">Básico</option>
                                        <option value="intermediate" selected>Intermedio</option>
                                        <option value="advanced">Avanzado</option>
                                    </select>
                                </div>
                                <div class="qb-field qb-field-full">
                                    <label>Contexto adicional <span style="color:#8C8C8C;font-weight:400">(opcional)</span></label>
                                    <input id="qb-ai-context" type="text" class="qb-input"
                                        placeholder="Ej: énfasis en normas de seguridad, casos prácticos...">
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:0.875rem;flex-wrap:wrap;">
                                <button class="qb-btn qb-btn-ai" id="qb-ai-gen-btn" onclick="QuizBuilder.generateWithAI()">
                                    ✨ Generar preguntas
                                </button>
                                <label class="qb-checkbox-label" style="font-size:0.78rem;">
                                    <input type="checkbox" id="qb-ai-replace">
                                    <span>Reemplazar preguntas existentes</span>
                                </label>
                            </div>
                            <div id="qb-ai-status" style="display:none;margin-top:0.875rem;"></div>
                        </div>
                    </div>
                </div>

                <!-- Preguntas -->
                <div class="qb-section">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                        <div class="qb-section-title" style="margin-bottom:0">Preguntas <span id="qb-q-count" style="font-weight:400;color:#8C8C8C;"></span></div>
                        <button class="qb-btn qb-btn-add" onclick="QuizBuilder.addQuestion()">+ Agregar pregunta</button>
                    </div>
                    <div id="qb-questions-list"></div>
                    <div id="qb-empty-msg" style="text-align:center;color:#8C8C8C;font-size:0.85rem;padding:2rem;background:#F8FAFB;border-radius:10px;border:1.5px dashed #D0DCE8;">
                        Sin preguntas todavía. Usa <strong>Generar con IA</strong> o haz clic en "Agregar pregunta".
                    </div>
                </div>

            </div>

            <div id="qb-footer">
                <button class="qb-btn qb-btn-danger" id="qb-delete-btn" onclick="QuizBuilder.deleteQuiz()" style="display:none">🗑 Eliminar quiz</button>
                <div style="display:flex;gap:0.625rem;margin-left:auto;">
                    <button class="qb-btn qb-btn-outline" onclick="QuizBuilder.close()">Cancelar</button>
                    <button class="qb-btn qb-btn-save" onclick="QuizBuilder.save()">💾 Guardar quiz</button>
                </div>
            </div>
        </div>`;
        document.body.appendChild(m);
        _injectStyles();
    }

    function _injectStyles() {
        if (document.getElementById('qb-styles')) return;
        const s = document.createElement('style');
        s.id = 'qb-styles';
        s.textContent = `
        #qb-modal{position:fixed;inset:0;z-index:9000;display:none;}
        #qb-backdrop{position:absolute;inset:0;background:rgba(0,40,80,0.45);backdrop-filter:blur(3px);}
        #qb-panel{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:min(740px,96vw);max-height:92vh;background:#fff;border-radius:16px;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.25);overflow:hidden;}
        #qb-header{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;background:linear-gradient(135deg,#003C64,#0096DC);flex-shrink:0;}
        #qb-header-left{display:flex;align-items:center;gap:0.75rem;}
        #qb-header-icon{font-size:1.3rem;}
        #qb-header-title{font-family:'Nunito Sans',sans-serif;font-weight:800;color:white;font-size:0.95rem;}
        #qb-header-sub{font-size:0.72rem;color:rgba(255,255,255,0.7);}
        #qb-close-btn{background:rgba(255,255,255,0.15);border:none;color:white;border-radius:6px;width:28px;height:28px;cursor:pointer;font-size:0.875rem;display:flex;align-items:center;justify-content:center;}
        #qb-body{flex:1;overflow-y:auto;padding:1.25rem;}
        .qb-section{margin-bottom:1.5rem;}
        .qb-section-title{font-family:'Nunito Sans',sans-serif;font-weight:800;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.07em;color:#003C64;margin-bottom:0.875rem;}
        .qb-config-grid{display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;}
        .qb-field{display:flex;flex-direction:column;gap:0.3rem;}
        .qb-field label{font-size:0.78rem;font-weight:700;color:#4A4A4A;}
        .qb-field-full{grid-column:1/-1;}
        .qb-checkbox-label{display:flex;align-items:center;gap:0.5rem;font-size:0.82rem;font-weight:600;color:#1A1A1A;cursor:pointer;}
        .qb-input{border:1.5px solid #D0DCE8;border-radius:8px;padding:0.5rem 0.75rem;font-size:0.85rem;font-family:'Open Sans',sans-serif;outline:none;transition:border 0.15s;}
        .qb-input:focus{border-color:#0099D8;}

        /* Card IA */
        .qb-ai-card{background:linear-gradient(135deg,#f0f8ff,#e8f4fb);border:1.5px solid #B3D9F0;border-radius:12px;overflow:hidden;}
        .qb-ai-header{display:flex;align-items:center;gap:0.75rem;padding:0.875rem 1.1rem;}
        .qb-ai-icon{font-size:1.25rem;flex-shrink:0;}
        .qb-ai-title{font-family:'Nunito Sans',sans-serif;font-weight:800;font-size:0.88rem;color:#003C64;}
        .qb-ai-sub{font-size:0.72rem;color:#0077B6;margin-top:0.1rem;}
        .qb-ai-toggle{margin-left:auto;background:white;border:1.5px solid #B3D9F0;color:#003C64;border-radius:8px;padding:0.3rem 0.75rem;font-size:0.75rem;font-weight:700;cursor:pointer;white-space:nowrap;font-family:'Nunito Sans',sans-serif;transition:all 0.15s;flex-shrink:0;}
        .qb-ai-toggle:hover{background:#003C64;color:white;border-color:#003C64;}
        #qb-ai-body{padding:0 1.1rem 1.1rem;border-top:1px solid #C8E6F5;}
        .qb-ai-fields{display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin:0.875rem 0;}
        .qb-ai-spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%;animation:qb-spin 0.7s linear infinite;vertical-align:middle;margin-right:0.4rem;}
        @keyframes qb-spin{to{transform:rotate(360deg)}}
        .qb-ai-ok{background:#ECFDF5;border:1px solid #6EE7B7;border-radius:8px;padding:0.6rem 0.875rem;font-size:0.8rem;color:#065F46;font-weight:600;}
        .qb-ai-err{background:#FEF2F2;border:1px solid #FCA5A5;border-radius:8px;padding:0.6rem 0.875rem;font-size:0.8rem;color:#991B1B;font-weight:600;}
        .qb-ai-loading{background:#FFF8E1;border:1px solid #FDE68A;border-radius:8px;padding:0.6rem 0.875rem;font-size:0.8rem;color:#856404;font-weight:600;}

        /* Pregunta */
        .qb-q-card{background:#F8FAFB;border:1.5px solid #E8EEF5;border-radius:12px;margin-bottom:0.75rem;overflow:hidden;}
        .qb-q-card.qb-q-new{animation:qb-fadein 0.25s ease;}
        @keyframes qb-fadein{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
        .qb-q-header{display:flex;align-items:center;gap:0.625rem;padding:0.75rem 1rem;background:#EEF4FB;border-bottom:1px solid #E8EEF5;}
        .qb-q-num{width:24px;height:24px;border-radius:50%;background:#003C64;color:white;font-size:0.72rem;font-weight:800;font-family:'Nunito Sans',sans-serif;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
        .qb-q-header-text{flex:1;font-weight:700;font-size:0.82rem;color:#003C64;}
        .qb-q-type-sel{font-size:0.78rem;border:1.5px solid #C4D0DC;border-radius:6px;padding:0.2rem 0.4rem;background:white;color:#1A1A1A;cursor:pointer;}
        .qb-q-del{background:none;border:none;color:#D97706;font-size:0.8rem;cursor:pointer;padding:0.2rem 0.4rem;border-radius:4px;}
        .qb-q-del:hover{background:#FEF3C7;}
        .qb-q-body{padding:0.875rem 1rem;}
        .qb-q-input{width:100%;border:1.5px solid #D0DCE8;border-radius:8px;padding:0.5rem 0.75rem;font-size:0.85rem;font-family:'Open Sans',sans-serif;margin-bottom:0.75rem;box-sizing:border-box;}
        .qb-q-input:focus{outline:none;border-color:#0099D8;}
        .qb-opts-list{display:flex;flex-direction:column;gap:0.4rem;margin-bottom:0.5rem;}
        .qb-opt-row{display:flex;align-items:center;gap:0.5rem;}
        .qb-opt-correct{width:18px;height:18px;accent-color:#10B981;cursor:pointer;flex-shrink:0;}
        .qb-opt-text{flex:1;border:1.5px solid #D0DCE8;border-radius:6px;padding:0.35rem 0.625rem;font-size:0.82rem;font-family:'Open Sans',sans-serif;}
        .qb-opt-text:focus{outline:none;border-color:#0099D8;}
        .qb-opt-del{background:none;border:none;color:#EF4444;cursor:pointer;font-size:0.8rem;padding:0.1rem 0.3rem;border-radius:4px;flex-shrink:0;}
        .qb-add-opt-btn{font-size:0.76rem;color:#0099D8;background:none;border:none;cursor:pointer;font-weight:700;padding:0.25rem 0;}
        .qb-points-row{display:flex;align-items:center;gap:0.5rem;margin-top:0.5rem;font-size:0.76rem;color:#6B6B6B;}
        .qb-points-row input{width:52px;border:1.5px solid #D0DCE8;border-radius:6px;padding:0.2rem 0.4rem;font-size:0.82rem;text-align:center;}

        /* Footer */
        #qb-footer{display:flex;align-items:center;padding:0.875rem 1.25rem;border-top:1.5px solid #E8EEF5;flex-shrink:0;background:#F8FAFB;}
        .qb-btn{padding:0.55rem 1.1rem;border-radius:8px;font-family:'Nunito Sans',sans-serif;font-weight:700;font-size:0.84rem;border:none;cursor:pointer;transition:all 0.15s;}
        .qb-btn-add{background:#E5F4FC;color:#003C64;border:1.5px solid #B3D9F0;}
        .qb-btn-add:hover{background:#CCE9F7;}
        .qb-btn-save{background:#003C64;color:white;}
        .qb-btn-save:hover{background:#0096DC;}
        .qb-btn-outline{background:transparent;color:#003C64;border:1.5px solid #003C64;}
        .qb-btn-danger{background:transparent;color:#EF4444;border:1.5px solid #EF4444;}
        .qb-btn-danger:hover{background:#FEF2F2;}
        .qb-btn-ai{background:linear-gradient(135deg,#004976,#0099D8);color:white;display:inline-flex;align-items:center;gap:0.35rem;}
        .qb-btn-ai:hover{opacity:0.88;}
        .qb-btn-ai:disabled{opacity:0.6;cursor:not-allowed;}

        /* Botón en lesson-item del form */
        .btn-quiz-lesson{font-size:0.72rem;background:#FFF4E6;color:#D97706;border:1.5px solid #FDDCAA;border-radius:6px;padding:0.25rem 0.6rem;cursor:pointer;font-family:'Nunito Sans',sans-serif;font-weight:700;white-space:nowrap;flex-shrink:0;transition:all 0.15s;}
        .btn-quiz-lesson.has-quiz{background:#ECFDF5;color:#065F46;border-color:#6EE7B7;}
        .btn-quiz-lesson:hover{opacity:0.8;}

        @media(max-width:600px){
            .qb-config-grid,.qb-ai-fields{grid-template-columns:1fr;}
            #qb-panel{max-height:98vh;width:98vw;}
        }`;
        document.head.appendChild(s);
    }

    async function openForLesson(lessonId, courseId, lessonTitle) {
        _lessonId    = lessonId;
        _courseId    = courseId;
        _lessonTitle = lessonTitle || '';
        _ensureModal();

        document.getElementById('qb-header-sub').textContent       = lessonTitle || 'Editor de preguntas';
        document.getElementById('qb-modal').style.display           = 'block';
        document.getElementById('qb-questions-list').innerHTML      = '';
        document.getElementById('qb-empty-msg').style.display       = 'block';
        document.getElementById('qb-delete-btn').style.display      = 'none';
        document.getElementById('qb-ai-status').style.display       = 'none';
        document.getElementById('qb-ai-body').style.display         = 'none';
        document.getElementById('qb-ai-toggle-btn').textContent     = 'Configurar ▾';
        document.getElementById('qb-cfg-title').value               = lessonTitle ? `Quiz: ${lessonTitle}` : 'Quiz de la Lección';
        document.getElementById('qb-cfg-passing').value             = '70';
        document.getElementById('qb-cfg-attempts').value            = '3';
        document.getElementById('qb-cfg-time').value                = '0';
        document.getElementById('qb-cfg-required').checked          = false;
        _updateQCount();

        try {
            const res  = await fetch('/admin/manage_quiz.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action:'get', lesson_id: lessonId})
            });
            const data = await res.json();
            if (data.success && data.quiz) {
                const q = data.quiz;
                document.getElementById('qb-cfg-title').value    = q.quiz_title;
                document.getElementById('qb-cfg-passing').value  = q.passing_score;
                document.getElementById('qb-cfg-attempts').value = q.max_attempts;
                document.getElementById('qb-cfg-time').value     = q.time_limit_min;
                document.getElementById('qb-cfg-required').checked = !!parseInt(q.is_required);
                document.getElementById('qb-delete-btn').style.display = 'inline-flex';
                q.questions.forEach(q2 => _addQuestionFromData(q2));
            }
        } catch(e) { console.error('QuizBuilder load error:', e); }
    }

    function close() {
        const m = document.getElementById('qb-modal');
        if (m) m.style.display = 'none';
    }

    // ── Toggle panel IA ────────────────────────────────────────────
    function _toggleAI() {
        const body = document.getElementById('qb-ai-body');
        const btn  = document.getElementById('qb-ai-toggle-btn');
        const open = body.style.display !== 'none';
        body.style.display = open ? 'none' : 'block';
        btn.textContent    = open ? 'Configurar ▾' : 'Ocultar ▴';
    }

    // ── Generar con Gemini ─────────────────────────────────────────
    async function generateWithAI() {
        const btn      = document.getElementById('qb-ai-gen-btn');
        const statusEl = document.getElementById('qb-ai-status');
        const num      = parseInt(document.getElementById('qb-ai-num').value)   || 5;
        const level    = document.getElementById('qb-ai-level').value           || 'intermediate';
        const context  = document.getElementById('qb-ai-context').value.trim();
        const replace  = document.getElementById('qb-ai-replace').checked;

        // Intentar leer descripción y título del curso desde el formulario padre
        const lessonDesc  = document.querySelector('input[name="lesson_description"]')?.value || '';
        const courseTitle = document.querySelector('input[name="course_title"]')?.value       || '';

        btn.disabled    = true;
        btn.innerHTML   = '<span class="qb-ai-spinner"></span> Generando...';
        statusEl.style.display = 'block';
        statusEl.className     = 'qb-ai-loading';
        statusEl.textContent   = '⏳ Gemini está creando las preguntas, espera unos segundos...';

        try {
            const res  = await fetch('/admin/generate_quiz.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({
                    lesson_title:       _lessonTitle,
                    lesson_description: lessonDesc,
                    course_title:       courseTitle,
                    difficulty:         level,
                    num_questions:      num,
                    extra_context:      context,
                })
            });
            const data = await res.json();

            if (data.success && data.questions?.length) {
                if (replace) document.getElementById('qb-questions-list').innerHTML = '';
                data.questions.forEach(q => _addQuestionFromData(q));
                _updateQCount();

                statusEl.className   = 'qb-ai-ok';
                statusEl.textContent = `✅ ${data.count} preguntas generadas${replace ? ' (anteriores reemplazadas)' : ' y agregadas al quiz'}.`;

                setTimeout(() => {
                    document.getElementById('qb-ai-body').style.display  = 'none';
                    document.getElementById('qb-ai-toggle-btn').textContent = 'Configurar ▾';
                    document.getElementById('qb-questions-list').scrollIntoView({behavior:'smooth',block:'start'});
                }, 1400);
            } else {
                statusEl.className   = 'qb-ai-err';
                statusEl.textContent = '❌ ' + (data.error || 'No se pudieron generar preguntas. Intenta de nuevo.');
            }
        } catch(e) {
            statusEl.className   = 'qb-ai-err';
            statusEl.textContent = '❌ Error de conexión con el servidor.';
        }

        btn.disabled  = false;
        btn.innerHTML = '✨ Generar preguntas';
    }

    function addQuestion() {
        _addQuestionFromData(null);
    }

    function _addQuestionFromData(data) {
        const idx   = document.querySelectorAll('.qb-q-card').length;
        const uid   = `qbq_${Date.now()}_${Math.random().toString(36).slice(2,7)}`;
        const qtype = data?.question_type || 'single';
        const opts  = data?.options || [{option_text:'',is_correct:0},{option_text:'',is_correct:0}];

        const card = document.createElement('div');
        card.className = 'qb-q-card qb-q-new';
        card.id = uid;
        card.innerHTML = `
        <div class="qb-q-header">
            <div class="qb-q-num">${idx+1}</div>
            <div class="qb-q-header-text">Pregunta ${idx+1}</div>
            <select class="qb-q-type-sel" onchange="QuizBuilder._changeType('${uid}', this.value)">
                <option value="single"    ${qtype==='single'?'selected':''}>Opción única</option>
                <option value="multiple"  ${qtype==='multiple'?'selected':''}>Múltiple</option>
                <option value="truefalse" ${qtype==='truefalse'?'selected':''}>Verdadero/Falso</option>
            </select>
            <button class="qb-q-del" onclick="QuizBuilder._removeQuestion('${uid}')">✕ Eliminar</button>
        </div>
        <div class="qb-q-body">
            <textarea class="qb-q-input" rows="2" placeholder="Escribe la pregunta aquí...">${data?.question_text||''}</textarea>
            <div class="qb-opts-list" id="${uid}-opts"></div>
            <button class="qb-add-opt-btn" id="${uid}-addopt" onclick="QuizBuilder._addOpt('${uid}')" ${qtype==='truefalse'?'style=display:none':''}>+ Agregar opción</button>
            <div class="qb-points-row">
                <span>Puntos:</span>
                <input type="number" min="1" max="10" value="${data?.points||1}" class="qb-pts-input">
            </div>
        </div>`;

        document.getElementById('qb-questions-list').appendChild(card);
        document.getElementById('qb-empty-msg').style.display = 'none';

        const optsContainer = document.getElementById(`${uid}-opts`);
        if (qtype === 'truefalse') {
            _renderTrueFalseOpts(uid, optsContainer, opts);
        } else {
            opts.forEach(o => _addOptRow(uid, optsContainer, o.option_text, o.is_correct));
        }
        _updateQCount();
    }

    function _renderTrueFalseOpts(uid, container, existing) {
        container.innerHTML = '';
        [{text:'Verdadero'},{text:'Falso'}].forEach(o => {
            const correct = existing?.find(e => e.option_text === o.text)?.is_correct || 0;
            _addOptRow(uid, container, o.text, correct, true);
        });
    }

    function _addOptRow(uid, container, text='', isCorrect=0, fixed=false) {
        const row = document.createElement('div');
        row.className = 'qb-opt-row';
        row.innerHTML = `
            <input type="checkbox" class="qb-opt-correct" ${isCorrect?'checked':''} title="Marcar como correcta">
            <input type="text" class="qb-opt-text" placeholder="Opción de respuesta..." value="${String(text).replace(/"/g,'&quot;')}" ${fixed?'readonly':''}>
            ${fixed?'':` <button class="qb-opt-del" onclick="this.parentElement.remove()">✕</button>`}`;
        container.appendChild(row);
    }

    function _changeType(uid, newType) {
        const card      = document.getElementById(uid);
        const container = card.querySelector('.qb-opts-list');
        const addBtn    = card.querySelector('.qb-add-opt-btn');
        container.innerHTML = '';
        if (newType === 'truefalse') {
            _renderTrueFalseOpts(uid, container, []);
            if (addBtn) addBtn.style.display = 'none';
        } else {
            _addOptRow(uid, container); _addOptRow(uid, container);
            if (addBtn) addBtn.style.display = 'inline';
        }
    }

    function _addOpt(uid) {
        _addOptRow(uid, document.getElementById(`${uid}-opts`));
    }

    function _removeQuestion(uid) {
        document.getElementById(uid)?.remove();
        _renumber();
    }

    function _renumber() {
        document.querySelectorAll('.qb-q-card').forEach((c, i) => {
            const num = c.querySelector('.qb-q-num');
            const txt = c.querySelector('.qb-q-header-text');
            if (num) num.textContent = i + 1;
            if (txt) txt.textContent = `Pregunta ${i + 1}`;
        });
        if (!document.querySelectorAll('.qb-q-card').length) {
            document.getElementById('qb-empty-msg').style.display = 'block';
        }
        _updateQCount();
    }

    function _updateQCount() {
        const n  = document.querySelectorAll('.qb-q-card').length;
        const el = document.getElementById('qb-q-count');
        if (el) el.textContent = n ? `(${n})` : '';
    }

    async function save() {
        const title    = document.getElementById('qb-cfg-title').value.trim()    || 'Quiz de la Lección';
        const passing  = parseInt(document.getElementById('qb-cfg-passing').value)  || 70;
        const attempts = parseInt(document.getElementById('qb-cfg-attempts').value) || 3;
        const timeLim  = parseInt(document.getElementById('qb-cfg-time').value)     || 0;
        const required = document.getElementById('qb-cfg-required').checked ? 1 : 0;

        const questions = [];
        for (const card of document.querySelectorAll('.qb-q-card')) {
            const qtext = card.querySelector('.qb-q-input')?.value?.trim();
            const qtype = card.querySelector('.qb-q-type-sel')?.value || 'single';
            const pts   = parseInt(card.querySelector('.qb-pts-input')?.value) || 1;
            if (!qtext) { alert('Todas las preguntas deben tener texto.'); return; }

            const options = [];
            let hasCorrect = false;
            for (const row of card.querySelectorAll('.qb-opt-row')) {
                const otext     = row.querySelector('.qb-opt-text')?.value?.trim();
                const isCorrect = row.querySelector('.qb-opt-correct')?.checked ? 1 : 0;
                if (!otext) continue;
                if (isCorrect) hasCorrect = true;
                options.push({option_text: otext, is_correct: isCorrect});
            }
            if (options.length < 2) { alert(`"${qtext.substring(0,40)}..." necesita al menos 2 opciones.`); return; }
            if (!hasCorrect)         { alert(`"${qtext.substring(0,40)}..." debe tener al menos una respuesta correcta.`); return; }
            questions.push({question_text: qtext, question_type: qtype, points: pts, options});
        }

        if (!questions.length) { alert('Agrega al menos una pregunta al quiz.'); return; }

        const saveBtn = document.querySelector('.qb-btn-save');
        saveBtn.textContent = 'Guardando…'; saveBtn.disabled = true;

        try {
            const res  = await fetch('/admin/manage_quiz.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action:'save', lesson_id:_lessonId, course_id:_courseId,
                    quiz_title:title, passing_score:passing, max_attempts:attempts,
                    time_limit_min:timeLim, is_required:required, questions})
            });
            const data = await res.json();
            if (data.success) {
                const trigger = document.querySelector(`[data-lesson-quiz="${_lessonId}"]`);
                if (trigger) { trigger.textContent = '✅ Quiz'; trigger.classList.add('has-quiz'); }
                close();
            } else { alert('Error al guardar: ' + (data.error || 'desconocido')); }
        } catch(e) { alert('Error de conexión.'); }

        saveBtn.textContent = '💾 Guardar quiz'; saveBtn.disabled = false;
    }

    async function deleteQuiz() {
        if (!confirm('¿Eliminar el quiz de esta lección? Se borrarán también los intentos previos.')) return;
        try {
            const res  = await fetch('/admin/manage_quiz.php', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({action:'delete', lesson_id:_lessonId})
            });
            const data = await res.json();
            if (data.success) {
                const trigger = document.querySelector(`[data-lesson-quiz="${_lessonId}"]`);
                if (trigger) { trigger.textContent = '+ Quiz'; trigger.classList.remove('has-quiz'); }
                close();
            }
        } catch(e) { alert('Error de conexión.'); }
    }

    return { openForLesson, close, addQuestion, save, deleteQuiz, generateWithAI, _toggleAI, _changeType, _addOpt, _removeQuestion };
})();