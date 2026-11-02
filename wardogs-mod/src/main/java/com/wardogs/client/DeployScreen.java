package com.wardogs.client;

import com.wardogs.net.DeployPayload;
import net.minecraft.client.gui.GuiGraphics;
import net.minecraft.client.gui.components.Button;
import net.minecraft.client.gui.screens.Screen;
import net.minecraft.network.chat.Component;
import net.neoforged.neoforge.network.PacketDistributor;

import java.util.List;

/** The deployment board: one button per place the soldier may deploy to. */
public final class DeployScreen extends Screen {
    private final List<String> names;

    public DeployScreen(List<String> names) {
        super(Component.literal("Deployment board"));
        this.names = names;
    }

    @Override
    protected void init() {
        int w = 180, x = (width - w) / 2, y = height / 2 - names.size() * 12;
        for (int i = 0; i < names.size(); i++) {
            int index = i;
            addRenderableWidget(Button.builder(Component.literal("DEPLOY  ▸  " + names.get(i).toUpperCase()), b -> {
                PacketDistributor.sendToServer(new DeployPayload(index));
                onClose();
            }).bounds(x, y + i * 24, w, 20).build());
        }
    }

    @Override
    public void render(GuiGraphics g, int mx, int my, float pt) {
        renderBackground(g, mx, my, pt);
        g.drawCenteredString(font, "DEPLOYMENT BOARD", width / 2, height / 2 - names.size() * 12 - 22, 0xFFFFD54A);
        super.render(g, mx, my, pt);
    }
}
