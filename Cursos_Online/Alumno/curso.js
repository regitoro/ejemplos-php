const curso = document.getElementById("curso-aprendizaje");

if (curso) {
    const estado = document.getElementById("estado-actualizacion");
    const barra = document.getElementById("progreso-barra");
    const porcentajeTexto = document.getElementById("progreso-porcentaje");
    const resumen = document.getElementById("progreso-resumen");
    const accionProgreso = document.getElementById("progreso-accion");
    const barraAccesible = curso.querySelector('[role="progressbar"]');
    const resultadoCurso = document.getElementById("resultado-curso");

    curso.querySelectorAll(".form-check-leccion").forEach((formulario) => {
        formulario.addEventListener("submit", async (evento) => {
            evento.preventDefault();

            const boton = formulario.querySelector("button[type='submit']");
            const leccion = formulario.closest(".leccion-curso");
            if (!boton || !leccion || boton.disabled) return;

            boton.disabled = true;
            if (estado) estado.textContent = "";

            try {
                const respuesta = await fetch(formulario.action, {
                    method: "POST",
                    body: new FormData(formulario),
                    headers: { Accept: "application/json" },
                    credentials: "same-origin",
                });

                if (!respuesta.ok) throw new Error("No se pudo actualizar el progreso.");
                const resultado = await respuesta.json();
                if (!resultado.ok) throw new Error("No se pudo actualizar el progreso.");

                const completada = Boolean(resultado.completada);
                leccion.classList.toggle("hecha", completada);
                boton.setAttribute("aria-pressed", String(completada));
                boton.setAttribute("aria-label", completada ? "Marcar como pendiente" : "Marcar como completada");
                boton.title = completada ? "Marcar como pendiente" : "Marcar como completada";

                const insignia = leccion.querySelector(".leccion-curso-badge");
                const accion = leccion.querySelector(".leccion-curso-accion");
                if (insignia) insignia.textContent = completada ? "Completada" : "Pendiente";
                if (accion) accion.firstChild.textContent = completada ? "Revisar lección " : "Ver lección ";

                const hechas = curso.querySelectorAll(".leccion-curso.hecha").length;
                const total = Number(curso.dataset.total || 0);
                const porcentaje = total ? Math.round((hechas / total) * 100) : 0;
                const leccionesCompletas = total > 0 && hechas === total;
                curso.dataset.hechas = String(hechas);
                if (barra) barra.style.width = `${porcentaje}%`;
                if (porcentajeTexto) porcentajeTexto.textContent = `${porcentaje}%`;
                if (resumen) resumen.textContent = `${hechas} de ${total} lecciones completadas`;
                if (barraAccesible) barraAccesible.setAttribute("aria-valuenow", String(porcentaje));

                if (resultadoCurso) {
                    resultadoCurso.hidden = !leccionesCompletas;
                    if (leccionesCompletas) {
                        const listo = resultadoCurso.dataset.ejerciciosListos === "true";
                        const aprobado = resultadoCurso.dataset.aprobado === "true";
                        const tituloResultado = document.getElementById("resultado-curso-titulo");
                        const mensajeResultado = document.getElementById("resultado-curso-mensaje");
                        const iconoResultado = document.getElementById("resultado-curso-icono");
                        const avisoNoAprobado = document.getElementById("resultado-curso-no-aprobado");
                        const enlaceCertificado = document.getElementById("enlace-certificado");
                        resultadoCurso.classList.toggle("resultado-aprobado", listo && aprobado);

                        if (!listo) {
                            if (iconoResultado) iconoResultado.textContent = "i";
                            const totalEjercicios = Number(resultadoCurso.dataset.ejerciciosTotal || 0);
                            const entregados = Number(resultadoCurso.dataset.ejerciciosEntregados || 0);
                            const calificados = Number(resultadoCurso.dataset.ejerciciosCalificados || 0);
                            if (tituloResultado) tituloResultado.textContent = "Contenido completado";
                            if (mensajeResultado) {
                                mensajeResultado.textContent = totalEjercicios === 0
                                    ? "Este curso no tiene ejercicios evaluables, por lo que no es posible calcular un promedio ni emitir el certificado de aprobación."
                                    : `Se requiere entregar y recibir calificación en todos los ejercicios. Entregados: ${entregados} de ${totalEjercicios}; calificados: ${calificados} de ${totalEjercicios}.`;
                            }
                        } else if (aprobado) {
                            if (iconoResultado) iconoResultado.textContent = "✓";
                            if (tituloResultado) tituloResultado.textContent = "¡Aprobaste el curso!";
                            if (mensajeResultado) mensajeResultado.textContent = "Completaste todas las lecciones y alcanzaste el mínimo aprobatorio de 80 puntos.";
                            if (avisoNoAprobado) avisoNoAprobado.hidden = true;
                            if (enlaceCertificado) enlaceCertificado.hidden = false;
                        } else {
                            if (iconoResultado) iconoResultado.textContent = "!";
                            if (tituloResultado) tituloResultado.textContent = "Resultado final";
                            if (mensajeResultado) mensajeResultado.textContent = "El promedio mínimo aprobatorio es 80 / 100.";
                            if (avisoNoAprobado) avisoNoAprobado.hidden = false;
                            if (enlaceCertificado) enlaceCertificado.hidden = true;
                        }
                    }
                }

                if (accionProgreso) {
                    accionProgreso.replaceChildren();
                    if (total > 0 && hechas === total) {
                        const completado = document.createElement("span");
                        completado.className = "curso-completado";
                        completado.textContent = "Curso completado";
                        accionProgreso.append(completado);
                    } else {
                        const siguiente = curso.querySelector(".leccion-curso:not(.hecha)");
                        if (siguiente) {
                            const enlace = document.createElement("a");
                            enlace.className = "curso-continuar";
                            enlace.href = `leccion.php?id=${siguiente.dataset.leccionId}`;
                            enlace.append(document.createTextNode("Continuar aprendizaje "));
                            const flecha = document.createElement("span");
                            flecha.setAttribute("aria-hidden", "true");
                            flecha.textContent = "→";
                            enlace.append(flecha);
                            accionProgreso.append(enlace);
                        }
                    }
                }

                const bloque = leccion.closest(".curso-bloque");
                if (bloque) {
                    const leccionesBloque = bloque.querySelectorAll(".leccion-curso");
                    const hechasBloque = bloque.querySelectorAll(".leccion-curso.hecha").length;
                    const textoBloque = bloque.querySelector(".curso-bloque-info > span");
                    const estadoBloque = bloque.querySelector(".curso-bloque-estado");
                    if (textoBloque) textoBloque.textContent = `${hechasBloque} de ${leccionesBloque.length} lecciones completadas`;
                    if (estadoBloque) estadoBloque.textContent = hechasBloque === leccionesBloque.length ? "Completo" : "En curso";
                }

                if (estado) estado.textContent = completada ? "Lección marcada como completada." : "Lección marcada como pendiente.";
            } catch (error) {
                if (estado) estado.textContent = error.message || "No se pudo guardar el cambio. Inténtalo de nuevo.";
            } finally {
                boton.disabled = false;
            }
        });
    });
}
