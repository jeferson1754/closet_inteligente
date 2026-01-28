# 👗 Closet Inteligente - Gestión de Vestuario con IA

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-stack&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-Datos-4479A1?style=flat-stack&logo=mysql)
![IA](https://img.shields.io/badge/IA-ChatGPT%20%7C%20Claude%20%7C%20Gemini-orange)

### 🚀 El Concepto
**Closet Inteligente** es una aplicación avanzada desarrollada para optimizar la organización y el uso del armario personal. El sistema trasciende el concepto de inventario estático, funcionando como un **asistente estratégico** que utiliza datos meteorológicos y modelos de Inteligencia Artificial para recomendar combinaciones ideales según el contexto del usuario.

---

## 🧠 Integración de IA y "Vibes Coding"
Este proyecto destaca por su flujo de trabajo moderno alineado con las tendencias actuales de desarrollo:
* **Ingeniería de Prompts:** Generación automática de instrucciones estructuradas para LLMs (**ChatGPT, Claude, Gemini**) basadas en el inventario real y el clima local.
* **Sugerencias Inteligentes:** Procesamiento de respuestas de IA para proponer conjuntos según categorías (Trabajo, Universidad, Eventos, Deportes).
* **Enfoque Builder:** Desarrollo iterativo apoyado en asistentes de IA para optimizar la lógica de negocio y la automatización de tareas.

## ✨ Capacidades Principales
* **Gestión Integral de Prendas:** Control detallado de atributos como material, color, nivel de formalidad y adecuación climática.
* **Panel de Análisis (Dashboard):** Visualización de datos con más de 10 gráficos que analizan patrones de uso, versatilidad de prendas y balance del vestuario.
* **Integración Meteorológica:** Conexión con la API de **OpenWeatherMap** para obtener pronósticos de 5 días en tiempo real.
* **Seguimiento y Rotación:** Control automático del estado de las prendas y límites de uso semanal para promover una rotación sostenible.
* **Automatización Crónica:** Procesos en segundo plano para la actualización diaria de estados y reinicios de contadores.

## 🛠️ Stack Tecnológico
* **Backend:** PHP y MySQL (Modelado de datos relacional).
* **Frontend:** HTML5, CSS3, JavaScript (Dashboard dinámico).
* **Integraciones:** OpenWeatherMap API y modelos externos de IA.

---

## 📂 Estructura del Proyecto
Para más detalles sobre la arquitectura, consulta los subsistemas:
1. **Arquitectura Central:** Diseño del núcleo y esquema de la base de datos.
2. **Gestión de Conjuntos:** Ciclo de vida de creación y seguimiento de outfits.
3. **Módulo de IA:** Lógica de integración y generación de sugerencias.
4. **Automatización:** Detalles de los trabajos programados y tareas de mantenimiento.

---

## 🔧 Instalación
1. Clonar: `git clone https://github.com/tu-usuario/closet-inteligente.git`
2. Configurar credenciales y API Key en `config.php`.
3. Ejecutar `setup.sql` para preparar el entorno de base de datos.
