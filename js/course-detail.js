function toggleModule(index) {
    const moduleContent = document.getElementById(`module-${index}`);
    const moduleHeader = moduleContent.previousElementSibling;
    const expandIcon = moduleHeader.querySelector('.expand-icon');
    
    if (moduleContent.style.maxHeight) {
        moduleContent.style.maxHeight = null;
        moduleContent.classList.remove('open');
        expandIcon.style.transform = 'rotate(0deg)';
    } else {
        moduleContent.style.maxHeight = moduleContent.scrollHeight + 'px';
        moduleContent.classList.add('open');
        expandIcon.style.transform = 'rotate(180deg)';
    }
}
async function enrollCourse(courseId) {
    const btn = event.target;
    const originalText = btn.textContent;
    
    btn.disabled = true;
    btn.textContent = 'Inscribiendo...';
    
    try {
        const response = await fetch('process/enroll_course.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `course_id=${courseId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.href = `course-view.php?id=${courseId}`;
        } else {
            alert(data.message);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al inscribirse al curso');
        btn.disabled = false;
        btn.textContent = originalText;
    }
}
document.addEventListener('DOMContentLoaded', function() {
    const firstModule = document.querySelector('.module-accordion');
    if (firstModule) {
        firstModule.querySelector('.module-header').click();
    }
});