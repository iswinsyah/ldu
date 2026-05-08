// Nama file: server.js

// 1. Impor library yang dibutuhkan
const express = require('express');
const cors = require('cors'); // Untuk mengizinkan request dari landing page
const { GoogleGenerativeAI } = require('@google/generative-ai');
const axios = require('axios'); // Untuk memanggil API Fonnte

// 2. Inisialisasi Aplikasi Express dan Konfigurasi API
const app = express();
const port = process.env.PORT || 3000; // Server akan berjalan di port 3000

// Ambil API Key dari environment variables (CARA AMAN!)
// Jangan pernah menulis API Key langsung di dalam kode.
const GEMINI_API_KEY = process.env.GEMINI_API_KEY;
const FONNTE_API_KEY = process.env.FONNTE_API_KEY;

const genAI = new GoogleGenerativeAI(GEMINI_API_KEY);
const model = genAI.getGenerativeModel({ model: "gemini-pro" });

// 3. Middleware
app.use(cors()); // Mengaktifkan CORS
app.use(express.json()); // Agar server bisa membaca data JSON dari request

// 4. Endpoint Utama untuk Menerima Data dari Landing Page
app.post('/webhook/leads-masuk', async (req, res) => {
    console.log("✅ Menerima data lead baru...");

    try {
        // Ambil data yang dikirim oleh form di landingpage1.html
        const { nama, whatsapp, harapan_user } = req.body;

        // Validasi data dasar
        if (!nama || !whatsapp || !harapan_user) {
            return res.status(400).json({ message: "Data tidak lengkap." });
        }

        // =================================================================
        // AI AGENT 2: ANALIS DATA (Menentukan Buyer Persona)
        // =================================================================
        console.log("🧠 AI Agent 2 (Analis) mulai bekerja...");
        const promptAnalis = `Sebagai seorang analis marketing spiritual, analisis data calon donatur ini:
        - Nama: ${nama}
        - Harapan Terbesar: "${harapan_user}"
        Tentukan Buyer Persona-nya dalam 3 kata kunci (contoh: Pekerja, Beban Finansial, Butuh Ketenangan).`;
        
        const resultAnalis = await model.generateContent(promptAnalis);
        const buyerPersona = resultAnalis.response.text();
        console.log(`-> Persona terdeteksi: ${buyerPersona}`);

        // =================================================================
        // AI AGENT 1: CS (Follow Up via Fonnte)
        // =================================================================
        console.log("📱 AI Agent 1 (CS) mulai bekerja...");
        const promptCS = `Anda adalah CS dari program sedekah Villa Quran. Buatlah pesan WhatsApp yang sangat ramah dan personal untuk ${nama} yang memiliki persona (${buyerPersona}).
        Isi pesan:
        1. Sapa dengan namanya.
        2. Ucapkan terima kasih telah tertarik dengan ebook "7 Kebiasaan Pembuka Rejeki ala Nabi".
        3. Berikan link download ebook ini: <https://link-ebook-bos.com/download>
        4. Ajak dia untuk download aplikasi "Panduan Hidup Berkah" sebagai pelengkap.
        Gunakan bahasa yang menyentuh dan sesuai dengan harapannya.`;

        const resultCS = await model.generateContent(promptCS);
        const pesanWA = resultCS.response.text();
        
        // Kirim pesan menggunakan Fonnte
        await axios.post('https://api.fonnte.com/send', {
            target: whatsapp,
            message: pesanWA
        }, {
            headers: { 'Authorization': FONNTE_API_KEY }
        });
        console.log(`-> Pesan WA terkirim ke ${whatsapp}`);

        // =================================================================
        // AI AGENT 3: CONTENT CALENDAR (Membuat Ide Konten)
        // =================================================================
        console.log("📅 AI Agent 3 (Content Calendar) mulai bekerja...");
        const promptKonten = `Berdasarkan persona donatur (${buyerPersona}), buatkan 1 ide konten video TikTok berdurasi 15 detik yang sangat relevan untuk menarik lebih banyak orang seperti dia.
        Format output harus berupa JSON dengan struktur: {"title": "Judul Video", "script": "Naskah voice over singkat", "visual_idea": "Ide visual per adegan"}`;

        const resultKonten = await model.generateContent(promptKonten);
        const ideKontenRaw = resultKonten.response.text();
        // Membersihkan output AI agar menjadi JSON valid
        const ideKonten = JSON.parse(ideKontenRaw.replace(/```json|```/g, ''));
        console.log(`-> Ide konten baru dibuat: ${ideKonten.title}`);

        // TODO: Simpan 'ideKonten' ini ke dalam database (MySQL, PostgreSQL, Supabase, dll)
        // await simpanKeDatabase(ideKonten);

        // Kirim respon sukses kembali ke landing page
        res.status(200).json({ message: "Proses funneling berhasil dijalankan!" });

    } catch (error) {
        console.error("❌ Terjadi kesalahan pada orkestrasi:", error.message);
        res.status(500).json({ message: "Terjadi kesalahan di server." });
    }
});

// 5. Menjalankan Server
app.listen(port, () => {
    console.log(`🚀 Server Orkestrasi AI berjalan di http://localhost:${port}`);
});
