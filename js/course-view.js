function toggleSidebar() {
    const sidebar = document.getElementById('viewer-sidebar');
    sidebar.classList.toggle('open');
}
document.addEventListener('DOMContentLoaded', function() {
    const lessonLinks = document.querySelectorAll('.lesson-link');
    
    lessonLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                setTimeout(() => {
                    toggleSidebar();
                }, 100);
            }
        });
    });
});
async function toggleLessonComplete(lessonId, courseId) {
    const btn = document.getElementById('complete-btn');
    const btnText = document.getElementById('complete-text');
    const originalText = btnText.textContent;
    
    btn.disabled = true;
    btnText.textContent = 'Procesando...';
    
    try {
        const response = await fetch('process/update_lesson_progress.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `lesson_id=${lessonId}&course_id=${courseId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.is_completed) {
                btn.classList.add('completed');
                btnText.textContent = 'Completada';
            } else {
                btn.classList.remove('completed');
                btnText.textContent = 'Marcar como completada';
            }
            updateProgressBar(data.progress_percentage);
            updateSidebarProgress(lessonId, data.is_completed);
            if (data.course_completed) {
                showCourseCompletionMessage();
            }
        } else {
            alert(data.message);
            btnText.textContent = originalText;
        }
        
        btn.disabled = false;
    } catch (error) {
        console.error('Error:', error);
        alert('Error al actualizar el progreso');
        btnText.textContent = originalText;
        btn.disabled = false;
    }
}
function updateProgressBar(percentage) {
    const progressFill = document.querySelector('.progress-bar-mini .progress-fill');
    const progressText = document.querySelector('.progress-text');
    
    if (progressFill) {
        progressFill.style.width = percentage + '%';
    }
    
    if (progressText) {
        progressText.textContent = Math.round(percentage) + '% completado';
    }
}
function updateSidebarProgress(lessonId, isCompleted) {
    const lessonLink = document.querySelector(`.lesson-link[href*="lesson=${lessonId}"]`);
    
    if (lessonLink) {
        if (isCompleted) {
            lessonLink.classList.add('completed');
            const icon = lessonLink.querySelector('.lesson-icon-small svg');
            if (icon) {
                icon.innerHTML = `
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                `;
            }
        } else {
            lessonLink.classList.remove('completed');
        }
        const moduleElement = lessonLink.closest('.sidebar-module');
        if (moduleElement) {
            const moduleCount = moduleElement.querySelector('.module-count');
            const lessonLinks = moduleElement.querySelectorAll('.lesson-link');
            const completedCount = moduleElement.querySelectorAll('.lesson-link.completed').length;
            
            if (moduleCount) {
                moduleCount.textContent = `${completedCount}/${lessonLinks.length}`;
            }
        }
    }
}
function showCourseCompletionMessage() {
    const message = document.createElement('div');
    message.className = 'completion-toast';
    message.innerHTML = `
        <div class="toast-content">
            <svg viewBox="0 0 20 20" fill="currentColor">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
            </svg>
            <div>
                <strong>¡Felicitaciones!</strong>
                <p>Has completado este curso</p>
            </div>
        </div>
    `;
    
    document.body.appendChild(message);
    
    setTimeout(() => {
        message.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        message.classList.remove('show');
        setTimeout(() => {
            message.remove();
        }, 300);
    }, 5000);
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const sidebar = document.getElementById('viewer-sidebar');
        if (sidebar.classList.contains('open')) {
            toggleSidebar();
        }
    }
});