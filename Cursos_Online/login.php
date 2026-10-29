<?php
session_start();
require_once "conexion.php";

$conexion = conectar();

$mensaje = "";
$abrirModalDirecto = false;

// Si ya existe sesión activa, verificamos para personalizar la experiencia
$sesionActiva = isset($_SESSION["usuario_id"]) && !empty($_SESSION["usuario_id"]);
$nombreSesion = $sesionActiva ? ($_SESSION["nombre"] ?? "Mi cuenta") : "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    // Buscar al usuario por su correo, junto con el nombre de su rol
    $sql = "SELECT usuarios.*, roles.nombre AS nombre_rol
            FROM usuarios
            INNER JOIN roles ON usuarios.rol_id = roles.id
            WHERE usuarios.email = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {

        $usuario = $resultado->fetch_assoc();

        if (!password_verify($password, $usuario["password"])) {

            $mensaje = "Correo o contraseña incorrectos.";
            $abrirModalDirecto = true;

        } elseif (!$usuario["email_verificado"]) {

            $mensaje = "Por favor, verifica tu correo electrónico antes de iniciar sesión.";
            $abrirModalDirecto = true;

        } elseif (!$usuario["aceptado"]) {

            // Cuenta aún no autorizada: mandar a la ventana de espera
            header("Location: pendiente.php");
            exit;

        } else {

            // Iniciar sesión
            session_regenerate_id(true);

            $_SESSION["usuario_id"] = $usuario["id"];
            $_SESSION["nombre"]     = $usuario["nombre"];
            $_SESSION["email"]      = $usuario["email"];
            $_SESSION["rol_id"]     = $usuario["rol_id"];
            $_SESSION["rol"]        = $usuario["nombre_rol"];

            header("Location: mainWindow.php");
            exit;
        }

    } else {

        $mensaje = "Correo o contraseña incorrectos.";
        $abrirModalDirecto = true;
    }

    $stmt->close();
}

// Abrir el modal si viene explícitamente el parámetro ?login=1 en la URL
if (isset($_GET["login"]) && $_GET["login"] === "1") {
    $abrirModalDirecto = true;
}
?>
<!DOCTYPE html>
<html lang="es" class="lx-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learnex | Aprende hoy. Transforma tu mañana</title>
    <meta name="description" content="Learnex es la plataforma educativa moderna que conecta a estudiantes, docentes y directores en un entorno de precisión para el aprendizaje continuo.">
    <link rel="stylesheet" href="learnex.css">
</head>
<body class="lx-body" id="inicio">

    <!-- ====================================================================
         BARRA DE NAVEGACIÓN (NAVBAR) — OBSIDIAN DARK
         ==================================================================== -->
    <header class="lx-navbar">
        <div class="lx-container lx-navbar-inner">
            <a href="#inicio" class="lx-brand" aria-label="Learnex Inicio">
                <div class="lx-logo-mark" aria-hidden="true">
                    <!-- Logotipo original Learnex con acento amatista / lavanda -->
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 4.5V18C4 19.38 5.12 20.5 6.5 20.5H19.5" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round"/>
                        <path d="M10 8L18 16" stroke="var(--color-lavender)" stroke-width="2.4" stroke-linecap="round"/>
                        <path d="M18 8L10 16" stroke="#ffffff" stroke-width="2.4" stroke-linecap="round"/>
                        <circle cx="18" cy="6" r="2" fill="var(--color-lavender)"/>
                    </svg>
                </div>
                <span class="lx-brand-name">Learnex<span class="lx-dot">.</span></span>
            </a>

            <!-- Enlaces de navegación desktop -->
            <nav>
                <ul class="lx-nav-links" id="lxNavMenu">
                    <li><a href="#inicio" class="lx-nav-link">Inicio</a></li>
                    <li>
                        <a href="#cursos" class="lx-nav-link">
                            Explorar cursos
                            <!-- <span class="lx-badge-soon">Próximamente</span> -->
                        </a>
                    </li>
                    <li><a href="#beneficios" class="lx-nav-link">Beneficios</a></li>
                    <li><a href="#roles" class="lx-nav-link">Roles</a></li>
                    <li><a href="#como-funciona" class="lx-nav-link">Cómo funciona</a></li>
                    <li><a href="#nosotros" class="lx-nav-link">Acerca de</a></li>
                </ul>
            </nav>

            <!-- Acciones de cuenta -->
            <div class="lx-nav-actions">
                <?php if ($sesionActiva): ?>
                    <a href="mainWindow.php" class="lx-btn lx-btn-outline">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                        Mi Panel (<?php echo htmlspecialchars($nombreSesion); ?>)
                    </a>
                    <a href="logout.php" class="lx-btn lx-btn-subtle" title="Cerrar sesión">Salir</a>
                <?php else: ?>
                    <button type="button" class="lx-btn lx-btn-outline" onclick="abrirModalLogin()">
                        Iniciar sesión
                    </button>
                    <a href="registro.php" class="lx-btn lx-btn-primary">
                        Crear cuenta
                    </a>
                <?php endif; ?>

                <!-- Toggle móvil -->
                <button type="button" class="lx-menu-toggle" id="lxMenuToggle" aria-label="Abrir menú" onclick="toggleMenuMovil()">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <!-- ====================================================================
         SECCIÓN PRINCIPAL (HERO) — OBSIDIAN DARK
         ==================================================================== -->
    <section class="lx-hero">
        <div class="lx-container">
            <div class="lx-hero-grid">
                
                <!-- Columna izquierda: Información comercial y CTA -->
                <div class="lx-hero-content">
                    <div class="lx-pill-kicker">
                        <span class="lx-kicker-dot"></span>
                        Plataforma Educativa Learnex
                    </div>

                    <h1 class="lx-hero-title">
                        Tu próximo gran logro comienza con lo que <span class="lx-gradient-text">aprendes hoy</span>.
                    </h1>

                    <p class="lx-hero-desc">
                        Descubre nuevas habilidades, aprende con cursos diseñados para ti y lleva tus conocimientos más lejos. En Learnex transformamos el aprendizaje en una experiencia práctica, dinámica y llena de oportunidades.
                    </p>

                    <div class="lx-hero-actions">
                        <a href="#cursos" class="lx-btn lx-btn-primary lx-btn-lg">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            Explorar cursos
                        </a>
                        <a href="#roles" class="lx-btn lx-btn-outline lx-btn-lg">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            Conocer los roles
                        </a>
                    </div>

                    <!-- Indicadores de confianza -->
                    <div class="lx-hero-trust">
                        <div class="lx-trust-item">
                            <svg class="lx-trust-icon" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="lx-trust-text">100% interactivo</span>
                        </div>
                        <div class="lx-trust-item">
                            <svg class="lx-trust-icon" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="lx-trust-text">Docentes y ponentes expertos</span>
                        </div>
                        <div class="lx-trust-item">
                            <svg class="lx-trust-icon" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="lx-trust-text">Certificación verificada</span>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha: Composición gráfica y Mockup de plataforma -->
                <div class="lx-hero-visual">
                    <div class="lx-mockup-wrapper">
                        
                        <!-- Tarjeta flotante superior: Comunidad activa -->
                        <div class="lx-floating-chip lx-chip-students">
                            <div class="lx-chip-avatar-group">
                                <span class="lx-avatar-sm" style="background:#7c3aed; color:#fff;">AL</span>
                                <span class="lx-avatar-sm" style="background:#4ade80; color:#171717;">MA</span>
                                <span class="lx-avatar-sm" style="background:#a78bfa; color:#171717;">CR</span>
                            </div>
                            <div>
                                <div class="lx-chip-title">+2,400 estudiantes</div>
                                <div class="lx-chip-sub">Comunidad activa en Learnex</div>
                            </div>
                        </div>

                        <!-- Tarjeta principal de previsualización (Dark Precision Surface) -->
                        <div class="lx-mockup-card-main">
                            <div class="lx-card-header-badge">
                                <span class="lx-category-tag">Especialización Profesional</span>
                                <div class="lx-status-live">
                                    <span class="lx-status-dot"></span> En progreso
                                </div>
                            </div>

                            <h2 class="lx-mockup-course-title">Desarrollo Web Full-Stack & Aplicaciones</h2>
                            
                            <div class="lx-mockup-meta">
                                <span>📚 8 Módulos</span>
                                <span>⏱ 36 Lecciones</span>
                                <span>⭐ 4.9 (Valoración)</span>
                            </div>

                            <!-- Progreso actual -->
                            <div class="lx-progress-block">
                                <div class="lx-progress-header">
                                    <span>Tu avance en el curso</span>
                                    <span style="color: var(--color-lavender); font-weight: 600;">78% completado</span>
                                </div>
                                <div class="lx-progress-bar-bg">
                                    <div class="lx-progress-bar-fill"></div>
                                </div>
                            </div>

                            <!-- Siguiente lección -->
                            <div class="lx-mockup-lesson-preview">
                                <div class="lx-lesson-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                    </svg>
                                </div>
                                <div class="lx-lesson-info">
                                    <span class="lx-lesson-label">Continuar lección</span>
                                    <div class="lx-lesson-name">Bloque 4: Arquitectura de Software y Práctica</div>
                                </div>
                                <button type="button" class="lx-btn lx-btn-outline" style="padding: 6px 12px; font-size: 0.8rem;" onclick="abrirModalLogin()">
                                    Ir
                                </button>
                            </div>
                        </div>

                        <!-- Tarjeta flotante inferior: Acreditación -->
                        <div class="lx-floating-chip lx-chip-certificate">
                            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--color-tag-background); color: var(--color-lavender); display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(167, 139, 250, 0.25);">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="8" r="7"></circle>
                                    <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                                </svg>
                            </div>
                            <div>
                                <div class="lx-chip-title">Certificado Acreditado</div>
                                <div class="lx-chip-sub">Emisión oficial al concluir</div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ====================================================================
         SECCIÓN DE BENEFICIOS
         ==================================================================== -->
    <section class="lx-section lx-section-alt" id="beneficios">
        <div class="lx-container">
            <div class="lx-section-header">
                <span class="lx-section-tag">Ventajas competitivas</span>
                <h2 class="lx-section-title">¿Por qué aprender en Learnex?</h2>
                <p class="lx-section-desc">
                    Diseñamos cada elemento de nuestra plataforma pensando en tu evolución constante, con herramientas que garantizan un aprendizaje enfocado, práctico y continuo.
                </p>
            </div>

            <div class="lx-benefits-grid">
                
                <!-- Beneficio 1 -->
                <div class="lx-benefit-card">
                    <div class="lx-benefit-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <h3 class="lx-benefit-title">Aprende a tu ritmo</h3>
                    <p class="lx-benefit-text">
                        Accede al contenido de tus cursos en cualquier momento y organiza tus sesiones de estudio con total flexibilidad según tu disponibilidad.
                    </p>
                </div>

                <!-- Beneficio 2 -->
                <div class="lx-benefit-card">
                    <div class="lx-benefit-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
                        </svg>
                    </div>
                    <h3 class="lx-benefit-title">Practica lo aprendido</h3>
                    <p class="lx-benefit-text">
                        Refuerza tus conocimientos con ejercicios reales, cuestionarios dinámicos y actividades diseñadas para consolidar cada concepto clave.
                    </p>
                </div>

                <!-- Beneficio 3 -->
                <div class="lx-benefit-card">
                    <div class="lx-benefit-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="lx-benefit-title">Recibe retroalimentación</h3>
                    <p class="lx-benefit-text">
                        Consulta las evaluaciones y comentarios personalizados de los ponentes para perfeccionar tu dominio y resolver dudas específicas.
                    </p>
                </div>

                <!-- Beneficio 4 -->
                <div class="lx-benefit-card">
                    <div class="lx-benefit-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                    </div>
                    <h3 class="lx-benefit-title">Sigue tu progreso</h3>
                    <p class="lx-benefit-text">
                        Identifica tus avances con métricas claras y precisas, visualiza las lecciones completadas y descubre exactamente qué puedes mejorar.
                    </p>
                </div>

                <!-- Beneficio 5 -->
                <div class="lx-benefit-card">
                    <div class="lx-benefit-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <h3 class="lx-benefit-title">Aprende en comunidad</h3>
                    <p class="lx-benefit-text">
                        Conecta con una experiencia educativa estimulante que fomenta la colaboración y el enriquecedor intercambio de conocimientos.
                    </p>
                </div>

                <!-- Beneficio 6 -->
                <div class="lx-benefit-card">
                    <div class="lx-benefit-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                    </div>
                    <h3 class="lx-benefit-title">Certificado de Aprovechamiento</h3>
                    <p class="lx-benefit-text">
                        Cada curso concluido exitosamente genera un certificado oficial que valida tus competencias ante instituciones y empleadores.
                    </p>
                </div>

            </div>
        </div>
    </section>

    <!-- ====================================================================
         SECCIÓN ROLES DE LA PLATAFORMA
         ==================================================================== -->
    <section class="lx-section" id="roles">
        <div class="lx-container">
            <div class="lx-section-header">
                <span class="lx-section-tag">Ecosistema Educativo</span>
                <h2 class="lx-section-title">Roles que integran Learnex</h2>
                <p class="lx-section-desc">
                    Una plataforma integral diseñada para coordinar armónicamente a directores, instructores, administradores y estudiantes en un entorno educativo estructurado.
                </p>
            </div>

            <div class="lx-roles-grid">
                
                <!-- Rol: Administrador -->
                <div class="lx-role-card lx-role-admin">
                    <div class="lx-role-top">
                        <div class="lx-role-icon">🛡️</div>
                        <span class="lx-role-badge">Supervisión</span>
                    </div>
                    <h3 class="lx-role-title">Administrador</h3>
                    <p class="lx-role-summary">Garantiza el correcto funcionamiento institucional y la calidad de los programas.</p>
                    <ul class="lx-role-features">
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Gestión integral de usuarios y permisos
                        </li>
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Aprobación o solicitud de cambios en cursos
                        </li>
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Autorización de cuentas pendientes
                        </li>
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Control global de inscripciones y reportes
                        </li>
                    </ul>
                </div>

                <!-- Rol: Director Académico -->
                <div class="lx-role-card lx-role-director">
                    <div class="lx-role-top">
                        <div class="lx-role-icon">🎯</div>
                        <span class="lx-role-badge">Coordinación</span>
                    </div>
                    <h3 class="lx-role-title">Director Académico</h3>
                    <p class="lx-role-summary">Lidera el diseño curricular y coordina a los equipos docentes.</p>
                    <ul class="lx-role-features">
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Creación y planeación de cursos y módulos
                        </li>
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Asignación de ponentes especializados
                        </li>
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Envío de programas a revisión oficial
                        </li>
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Supervisión de alumnos y solicitudes
                        </li>
                    </ul>
                </div>

                <!-- Rol: Ponente / Docente -->
                <div class="lx-role-card lx-role-ponente">
                    <div class="lx-role-top">
                        <div class="lx-role-icon">👨‍🏫</div>
                        <span class="lx-role-badge">Docencia</span>
                    </div>
                    <h3 class="lx-role-title">Ponente / Docente</h3>
                    <p class="lx-role-summary">Imparte conocimiento, orienta a los alumnos y guía la práctica formativa.</p>
                    <ul class="lx-role-features">
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Diseño de lecciones multimedia y ejercicios
                        </li>
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Atención personalizada a dudas y preguntas
                        </li>
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Evaluación y calificación con feedback
                        </li>
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Seguimiento individual del aprendizaje
                        </li>
                    </ul>
                </div>

                <!-- Rol: Alumno / Estudiante -->
                <div class="lx-role-card lx-role-alumno">
                    <div class="lx-role-top">
                        <div class="lx-role-icon">🎓</div>
                        <span class="lx-role-badge">Estudiante</span>
                    </div>
                    <h3 class="lx-role-title">Alumno / Estudiante</h3>
                    <p class="lx-role-summary">El protagonista de la experiencia educativa y del desarrollo de habilidades.</p>
                    <ul class="lx-role-features">
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Exploración e inscripción en cursos
                        </li>
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Estudio de lecciones y quizzes interactivos
                        </li>
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Entrega de actividades prácticas
                        </li>
                        <li class="lx-role-feature-item">
                            <svg class="lx-role-check" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Certificados con validez al concluir
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </section>

    <!-- ====================================================================
         SECCIÓN CÓMO FUNCIONA
         ==================================================================== -->
    <section class="lx-section lx-section-alt" id="como-funciona">
        <div class="lx-container">
            <div class="lx-section-header">
                <span class="lx-section-tag">Ruta de aprendizaje</span>
                <h2 class="lx-section-title">Comenzar es simple y rápido</h2>
                <p class="lx-section-desc">
                    Tres pasos diseñados para llevarte desde tu registro hasta el dominio práctico de nuevas habilidades.
                </p>
            </div>

            <div class="lx-steps-container">
                
                <!-- Paso 1 -->
                <div class="lx-step-card">
                    <span class="lx-step-number-pill">01</span>
                    <div class="lx-step-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                    </div>
                    <h3 class="lx-step-title">Crea tu cuenta</h3>
                    <p class="lx-step-desc">
                        Regístrate en pocos segundos y accede a las opciones disponibles para tu perfil dentro de la plataforma.
                    </p>
                </div>

                <!-- Paso 2 -->
                <div class="lx-step-card">
                    <span class="lx-step-number-pill">02</span>
                    <div class="lx-step-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </div>
                    <h3 class="lx-step-title">Encuentra tu curso</h3>
                    <p class="lx-step-desc">
                        Descubre las opciones de aprendizaje que se ajusten a tus intereses, metas y nivel de experiencia.
                    </p>
                </div>

                <!-- Paso 3 -->
                <div class="lx-step-card">
                    <span class="lx-step-number-pill">03</span>
                    <div class="lx-step-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <h3 class="lx-step-title">Aprende y practica</h3>
                    <p class="lx-step-desc">
                        Estudia el contenido en lecciones dinámicas, resuelve ejercicios interactivos y consulta tu progreso en tiempo real.
                    </p>
                </div>

            </div>
        </div>
    </section>

    <!-- ====================================================================
         SECCIÓN EXPLORAR CURSOS (PREPARADA PARA EL CATÁLOGO PÚBLICO)
         ==================================================================== -->
    <section class="lx-section" id="cursos">
        <div class="lx-container">
            <div class="lx-section-header">
                <span class="lx-section-tag">Oferta Educativa</span>
                <h2 class="lx-section-title">Explora las áreas de formación</h2>
                <p class="lx-section-desc">
                    El catálogo público de cursos estará disponible próximamente para consulta abierta. Si ya eres estudiante, inicia sesión para acceder a tus asignaturas activas.
                </p>
            </div>

            <!-- Preview del diseño preparado en Dark Theme -->
            <div class="lx-benefits-grid">
                <div class="lx-benefit-card" style="border-top: 2px solid var(--color-amethyst);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <span class="lx-category-tag">Tecnología & Software</span>
                        <span style="font-size: 0.8rem; color: var(--color-muted-gray);">4 Semanas</span>
                    </div>
                    <h3 class="lx-benefit-title" style="margin-bottom: 8px;">Fundamentos de Programación y Lógica</h3>
                    <p class="lx-benefit-text" style="margin-bottom: 20px;">
                        Aprende las estructuras fundamentales de desarrollo de software, algoritmos y buenas prácticas de ingeniería de código.
                    </p>
                    <div style="margin-top:auto; padding-top:16px; border-top:1px solid var(--color-graphite); display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.82rem; color:var(--color-muted-gray);">Ponente asignado</span>
                        <button type="button" class="lx-btn lx-btn-outline" style="padding: 6px 12px; font-size: 0.82rem;" onclick="abrirModalLogin()">Ver detalles</button>
                    </div>
                </div>

                <div class="lx-benefit-card" style="border-top: 2px solid var(--color-lavender);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <span class="lx-category-tag">Gestión & Proyectos</span>
                        <span style="font-size: 0.8rem; color: var(--color-muted-gray);">6 Semanas</span>
                    </div>
                    <h3 class="lx-benefit-title" style="margin-bottom: 8px;">Metodologías Ágiles y Dirección de Equipos</h3>
                    <p class="lx-benefit-text" style="margin-bottom: 20px;">
                        Coordina proyectos colaborativos aplicando Scrum, Kanban y marcos de trabajo modernos orientados a resultados.
                    </p>
                    <div style="margin-top:auto; padding-top:16px; border-top:1px solid var(--color-graphite); display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.82rem; color:var(--color-muted-gray);">Ponente asignado</span>
                        <button type="button" class="lx-btn lx-btn-outline" style="padding: 6px 12px; font-size: 0.82rem;" onclick="abrirModalLogin()">Ver detalles</button>
                    </div>
                </div>

                <div class="lx-benefit-card" style="border-top: 2px solid var(--color-success-green);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <span class="lx-category-tag" style="color:var(--color-success-green); background:var(--color-success-bg); border-color:rgba(74,222,128,0.25);">Diseño & UX</span>
                        <span style="font-size: 0.8rem; color: var(--color-muted-gray);">5 Semanas</span>
                    </div>
                    <h3 class="lx-benefit-title" style="margin-bottom: 8px;">Diseño de Experiencia de Usuario y Prototipado</h3>
                    <p class="lx-benefit-text" style="margin-bottom: 20px;">
                        Crea interfaces digitales centradas en las personas, arquitecturas de información y pruebas de usabilidad comprobadas.
                    </p>
                    <div style="margin-top:auto; padding-top:16px; border-top:1px solid var(--color-graphite); display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.82rem; color:var(--color-muted-gray);">Ponente asignado</span>
                        <button type="button" class="lx-btn lx-btn-outline" style="padding: 6px 12px; font-size: 0.82rem;" onclick="abrirModalLogin()">Ver detalles</button>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 36px;">
                <p style="font-size: 0.95rem; color: var(--color-medium-gray); margin-bottom: 16px;">
                    ¿Quieres acceder a todos los contenidos y solicitar tu inscripción?
                </p>
                <a href="registro.php" class="lx-btn lx-btn-primary">
                    Crear mi cuenta gratuita en Learnex
                </a>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         SECCIÓN INSTITUCIONAL / MISIÓN
         ==================================================================== -->
    <section class="lx-section lx-section-alt" id="nosotros">
        <div class="lx-container">
            <div class="lx-mission-box">
                <div>
                    <span class="lx-mission-badge">Nuestra Misión</span>
                    <h2 class="lx-mission-title">Aprender es crecer.<br>Crecer es avanzar.</h2>
                    <p class="lx-mission-text">
                        En Learnex creemos que aprender debe ser una experiencia accesible, dinámica y motivadora. Creamos un espacio donde estudiantes y profesionales pueden desarrollar nuevas habilidades, compartir conocimientos y crecer a su propio ritmo, dentro de una comunidad educativa que impulsa el aprendizaje y la mejora continua.
                    </p>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <button type="button" class="lx-btn lx-btn-primary" onclick="abrirModalLogin()">
                            Acceder a la plataforma
                        </button>
                        <a href="registro.php" class="lx-btn lx-btn-outline">
                            Unirse a la comunidad
                        </a>
                    </div>
                </div>

                <div class="lx-mission-pillars">
                    <div class="lx-pillar-card">
                        <div class="lx-pillar-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                            Crecimiento Continuo
                        </div>
                        <p class="lx-pillar-desc">Impulsamos tu evolución paso a paso mediante rutas de estudio graduales.</p>
                    </div>

                    <div class="lx-pillar-card">
                        <div class="lx-pillar-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                            Aprendizaje Práctico
                        </div>
                        <p class="lx-pillar-desc">Ejercicios enfocados en habilidades reales y aplicables desde el primer día.</p>
                    </div>

                    <div class="lx-pillar-card">
                        <div class="lx-pillar-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            Acompañamiento
                        </div>
                        <p class="lx-pillar-desc">Docentes y directores comprometidos con guiar y retroalimentar tu proceso.</p>
                    </div>

                    <div class="lx-pillar-card">
                        <div class="lx-pillar-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            Habilidades Clave
                        </div>
                        <p class="lx-pillar-desc">Programas actualizados alineados a las demandas del entorno profesional.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         LLAMADA FINAL A LA ACCIÓN (CTA)
         ==================================================================== -->
    <section class="lx-section" style="padding-top: 36px; padding-bottom: 36px;">
        <div class="lx-container">
            <div class="lx-cta-banner">
                <div class="lx-cta-content">
                    <h2 class="lx-cta-title">El siguiente paso en tu aprendizaje comienza aquí</h2>
                    <p class="lx-cta-desc">
                        Únete a Learnex y descubre todo lo que puedes lograr cuando aprender se convierte en una oportunidad.
                    </p>
                    <div class="lx-cta-actions">
                        <a href="registro.php" class="lx-btn lx-btn-cta-main">
                            Crear mi cuenta
                        </a>
                        <button type="button" class="lx-btn lx-btn-outline" onclick="abrirModalLogin()">
                            Ya tengo cuenta
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ====================================================================
         PIE DE PÁGINA (FOOTER)
         ==================================================================== -->
    <footer class="lx-footer">
        <div class="lx-container">
            <div class="lx-footer-grid">
                
                <!-- Columna Marca -->
                <div class="lx-footer-brand-col">
                    <a href="#inicio" class="lx-brand">
                        <div class="lx-logo-mark" aria-hidden="true" style="width: 30px; height: 30px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M4 4.5V18C4 19.38 5.12 20.5 6.5 20.5H19.5" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round"/>
                                <path d="M10 8L18 16" stroke="var(--color-lavender)" stroke-width="2.4" stroke-linecap="round"/>
                                <path d="M18 8L10 16" stroke="#ffffff" stroke-width="2.4" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <span class="lx-brand-name">Learnex<span class="lx-dot">.</span></span>
                    </a>
                    <p class="lx-footer-tagline">
                        «Aprende hoy. Transforma tu mañana».<br>
                        Plataforma educativa para el desarrollo de competencias prácticas y crecimiento profesional.
                    </p>
                </div>

                <!-- Columna Navegación -->
                <div>
                    <h4 class="lx-footer-col-title">Navegación</h4>
                    <ul class="lx-footer-links">
                        <li><a href="#inicio" class="lx-footer-link">Inicio</a></li>
                        <li><a href="#cursos" class="lx-footer-link">Explorar cursos</a></li>
                        <li><a href="#beneficios" class="lx-footer-link">Beneficios</a></li>
                        <li><a href="#roles" class="lx-footer-link">Roles de la plataforma</a></li>
                    </ul>
                </div>

                <!-- Columna Plataforma -->
                <div>
                    <h4 class="lx-footer-col-title">Plataforma</h4>
                    <ul class="lx-footer-links">
                        <li><a href="#como-funciona" class="lx-footer-link">Cómo funciona</a></li>
                        <li><a href="#nosotros" class="lx-footer-link">Acerca de Learnex</a></li>
                        <li><a href="javascript:void(0)" class="lx-footer-link" onclick="abrirModalLogin()">Iniciar sesión</a></li>
                        <li><a href="registro.php" class="lx-footer-link">Crear cuenta</a></li>
                    </ul>
                </div>

                <!-- Columna Soporte & Ayuda -->
                <div>
                    <h4 class="lx-footer-col-title">Soporte</h4>
                    <ul class="lx-footer-links">
                        <li><a href="recuperar_contrasena.php" class="lx-footer-link">Recuperar contraseña</a></li>
                        <li><a href="javascript:void(0)" class="lx-footer-link" onclick="mostrarModalLegal('terminos')">Términos y condiciones</a></li>
                        <li><a href="javascript:void(0)" class="lx-footer-link" onclick="mostrarModalLegal('privacidad')">Aviso de privacidad</a></li>
                    </ul>
                </div>

            </div>

            <!-- Barra inferior -->
            <div class="lx-footer-bottom">
                <div>
                    &copy; <?php echo date("Y"); ?> Learnex. Todos los derechos reservados.
                </div>
                <div class="lx-footer-bottom-links">
                    <a href="javascript:void(0)" onclick="mostrarModalLegal('privacidad')" class="lx-footer-link">Privacidad</a>
                    <a href="javascript:void(0)" onclick="mostrarModalLegal('terminos')" class="lx-footer-link">Términos</a>
                    <a href="#inicio" class="lx-footer-link">Volver arriba ↑</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- ====================================================================
         MÓDULO DE ACCESO / LOGIN (OBSIDIAN DARK — 2 COLUMNAS)
         Conserva intacta la lógica de autenticación backend de login.php
         ==================================================================== -->
    <div class="lx-modal-backdrop <?php echo $abrirModalDirecto ? 'lx-is-active' : ''; ?>" id="lxModalLogin" aria-modal="true" role="dialog" aria-labelledby="lxModalTitle">
        <div class="lx-auth-card-split">
            
            <!-- Botón cerrar modal -->
            <button type="button" class="lx-modal-close" onclick="cerrarModalLogin()" aria-label="Cerrar ventana de acceso">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>

            <!-- COLUMNA IZQUIERDA: Marca, Frase Promocional e Ilustración -->
            <div class="lx-auth-col-promo">
                <div class="lx-auth-promo-brand">
                    <div class="lx-logo-mark" style="width: 32px; height: 32px;" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M4 4.5V18C4 19.38 5.12 20.5 6.5 20.5H19.5" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round"/>
                            <path d="M10 8L18 16" stroke="var(--color-lavender)" stroke-width="2.4" stroke-linecap="round"/>
                            <path d="M18 8L10 16" stroke="#ffffff" stroke-width="2.4" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="lx-brand-name">Learnex<span class="lx-dot">.</span></span>
                </div>

                <div class="lx-auth-promo-content">
                    <span class="lx-auth-promo-badge">Acceso a la plataforma</span>
                    <h3 class="lx-auth-promo-heading">Continúa tu ruta de aprendizaje</h3>
                    <p class="lx-auth-promo-desc">
                        Conecta con tus ponentes, retoma tus lecciones donde las dejaste y descubre tus progresos formativos en un solo lugar.
                    </p>

                    <ul class="lx-auth-promo-points">
                        <li class="lx-auth-promo-point">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Acceso inmediato a tus cursos y materiales
                        </li>
                        <li class="lx-auth-promo-point">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Resolución de actividades y feedback continuo
                        </li>
                        <li class="lx-auth-promo-point">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Certificados oficiales acreditados
                        </li>
                    </ul>
                </div>

                <div class="lx-auth-promo-quote">
                    «Aprende hoy. Transforma tu mañana».
                </div>
            </div>

            <!-- COLUMNA DERECHA: Formulario Real de Autenticación -->
            <div class="lx-auth-col-form">
                <div class="lx-auth-form-header">
                    <h2 class="lx-auth-title" id="lxModalTitle">Iniciar sesión</h2>
                    <p class="lx-auth-sub">Ingresa tus credenciales para acceder a Learnex</p>
                </div>

                <!-- Mensaje de error o retroalimentación del backend existente -->
                <?php if (!empty($mensaje)): ?>
                    <div class="lx-alert-error" role="alert">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span><?php echo htmlspecialchars($mensaje); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Formulario POST conectado a la lógica existente -->
                <form method="POST" action="login.php" novalidate>
                    <div class="lx-form-group">
                        <label for="email" class="lx-form-label">Correo electrónico</label>
                        <div class="lx-input-wrapper">
                            <svg class="lx-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="lx-input" 
                                placeholder="tu@correo.com" 
                                autocomplete="email" 
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                required
                            >
                        </div>
                    </div>

                    <div class="lx-form-group">
                        <label for="password" class="lx-form-label">Contraseña</label>
                        <div class="lx-input-wrapper">
                            <svg class="lx-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="lx-input" 
                                placeholder="••••••••" 
                                autocomplete="current-password" 
                                required
                            >
                        </div>
                    </div>

                    <div class="lx-form-options">
                        <a href="recuperar_contrasena.php" class="lx-forgot-link">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>

                    <button type="submit" class="lx-btn-auth-submit">
                        Entrar a Learnex
                    </button>
                </form>

                <div class="lx-auth-footer">
                    ¿No tienes una cuenta aún? 
                    <a href="registro.php">Crear una cuenta</a>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal para Términos y Privacidad en Dark Theme -->
    <div class="lx-modal-backdrop" id="lxModalLegal" aria-modal="true" role="dialog">
        <div class="lx-modal-legal-card">
            <button type="button" class="lx-modal-close" onclick="cerrarModalLegal()" aria-label="Cerrar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <h3 id="lxLegalTitle" style="font-size:1.3rem; font-weight:700; margin:0 0 12px; color:var(--color-bright-gray);">Aviso</h3>
            <p id="lxLegalContent" style="font-size:0.92rem; color:var(--color-medium-gray); line-height:1.6; margin:0 0 20px;">
                Estructura preparada institucionalmente para Learnex.
            </p>
            <button type="button" class="lx-btn lx-btn-outline" style="width:100%;" onclick="cerrarModalLegal()">Entendido</button>
        </div>
    </div>

    <!-- ====================================================================
         INTERACTIVIDAD JAVASCRIPT
         ==================================================================== -->
    <script>
        function abrirModalLogin() {
            var modal = document.getElementById('lxModalLogin');
            if (modal) {
                modal.classList.add('lx-is-active');
                setTimeout(function() {
                    var emailInput = document.getElementById('email');
                    if (emailInput) { emailInput.focus(); }
                }, 100);
            }
        }

        function cerrarModalLogin() {
            var modal = document.getElementById('lxModalLogin');
            if (modal) {
                modal.classList.remove('lx-is-active');
            }
        }

        function toggleMenuMovil() {
            var nav = document.getElementById('lxNavMenu');
            if (nav) {
                nav.classList.toggle('lx-mobile-active');
            }
        }

        function mostrarModalLegal(tipo) {
            var modal = document.getElementById('lxModalLegal');
            var title = document.getElementById('lxLegalTitle');
            var content = document.getElementById('lxLegalContent');

            if (tipo === 'terminos') {
                title.innerText = 'Términos y Condiciones de Learnex';
                content.innerText = 'El uso de la plataforma Learnex está regulado por la normativa de convivencia académica, respeto entre pares y aprovechamiento ético de los contenidos y evaluaciones. Las políticas completas se integrarán conforme al reglamento institucional.';
            } else {
                title.innerText = 'Aviso de Privacidad';
                content.innerText = 'En Learnex nos comprometemos con la seguridad y protección de tus datos personales. La información de tu cuenta es utilizada exclusivamente para fines de autenticación, control escolar y seguimiento formativo.';
            }

            if (modal) {
                modal.classList.add('lx-is-active');
            }
        }

        function cerrarModalLegal() {
            var modal = document.getElementById('lxModalLegal');
            if (modal) {
                modal.classList.remove('lx-is-active');
            }
        }

        // Cerrar modales con tecla Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModalLogin();
                cerrarModalLegal();
            }
        });

        // Cerrar si se da clic en el backdrop exterior
        window.addEventListener('click', function(e) {
            var modalLogin = document.getElementById('lxModalLogin');
            if (e.target === modalLogin) {
                cerrarModalLogin();
            }
            var modalLegal = document.getElementById('lxModalLegal');
            if (e.target === modalLegal) {
                cerrarModalLegal();
            }
        });

        // Si la URL contiene el hash #login, abrir modal
        if (window.location.hash === '#login') {
            abrirModalLogin();
        }
    </script>
</body>
</html>
