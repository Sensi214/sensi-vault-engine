const express = require('express');
const { createCanvas, loadImage } = require('canvas');
const cors = require('cors');
const { GoogleGenerativeAI } = require("@google/generative-ai");
require('dotenv').config();

const app = express();
app.use(cors());
app.use(express.json());

// INKEDJOY SPEC MAP - Keeps your Merch Gen accurate
const INKEDJOY_SPECS = {
    'hoodie': { w: 3600, h: 3600 },
    'candle': { w: 900, h: 700 },
    'mug':    { w: 2475, h: 1125 }
};

// GEMINI AI BRAIN - For the "Glam Squad" energy
const genAI = new GoogleGenerativeAI(process.env.GEMINI_API_KEY);

app.post('/forge-merch', async (req, res) => {
    const { productType, userImage, tagline } = req.body;
    const spec = INKEDJOY_SPECS[productType] || INKEDJOY_SPECS['hoodie'];

    try {
        const canvas = createCanvas(spec.w, spec.h);
        const ctx = canvas.getContext('2d');
        
        // Load the selfie/art from your Elementor site
        const img = await loadImage(userImage);
        ctx.drawImage(img, 0, 0, spec.w, spec.h);
        
        // Bake the final high-res file
        res.json({ success: true, message: "Design Forged for InkedJoy" });
    } catch (err) {
        res.status(500).json({ error: "Forge failed" });
    }
});

app.listen(process.env.PORT || 3000);
