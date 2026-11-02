package com.wardogs.decor;

import com.mojang.serialization.MapCodec;
import net.minecraft.core.BlockPos;
import net.minecraft.core.Direction;
import net.minecraft.world.item.context.BlockPlaceContext;
import net.minecraft.world.level.BlockGetter;
import net.minecraft.world.level.block.HorizontalDirectionalBlock;
import net.minecraft.world.level.block.state.BlockState;
import net.minecraft.world.level.block.state.StateDefinition;
import net.minecraft.world.phys.shapes.CollisionContext;
import net.minecraft.world.phys.shapes.Shapes;
import net.minecraft.world.phys.shapes.VoxelShape;

import java.util.List;

/** A furniture / street-furniture / camp block with a box-built model; faces the player who placed it. */
public class DecorBlock extends HorizontalDirectionalBlock {
    public static final MapCodec<DecorBlock> CODEC = simpleCodec(p -> new DecorBlock(p, List.of(new double[]{0, 0, 0, 16, 16, 16}), true));

    private final VoxelShape[] shapes = new VoxelShape[4]; // by Direction.get2DDataValue(): south, west, north, east
    private final boolean solid;

    public DecorBlock(Properties props, List<double[]> boxesNorth, boolean solid) {
        super(props);
        this.solid = solid;
        registerDefaultState(stateDefinition.any().setValue(FACING, Direction.NORTH));
        for (Direction d : Direction.Plane.HORIZONTAL) {
            VoxelShape s = Shapes.empty();
            for (double[] b : boxesNorth) {
                s = Shapes.or(s, rotated(b, d));
            }
            shapes[d.get2DDataValue()] = s;
        }
    }

    /** A box given for the north-facing model, turned to face {@code d} (clockwise quarter turns north -> east -> south -> west). */
    static VoxelShape rotated(double[] b, Direction d) {
        double x0 = b[0], y0 = b[1], z0 = b[2], x1 = b[3], y1 = b[4], z1 = b[5];
        int turns = switch (d) {
            case EAST -> 1;
            case SOUTH -> 2;
            case WEST -> 3;
            default -> 0;
        };
        for (int i = 0; i < turns; i++) {
            double nx0 = 16 - z1, nx1 = 16 - z0, nz0 = x0, nz1 = x1;
            x0 = nx0; x1 = nx1; z0 = nz0; z1 = nz1;
        }
        return Shapes.box(Math.max(0, x0) / 16, y0 / 16, Math.max(0, z0) / 16, Math.min(16, x1) / 16, y1 / 16, Math.min(16, z1) / 16);
    }

    @Override
    protected MapCodec<? extends HorizontalDirectionalBlock> codec() {
        return CODEC;
    }

    @Override
    protected void createBlockStateDefinition(StateDefinition.Builder<net.minecraft.world.level.block.Block, BlockState> b) {
        b.add(FACING);
    }

    @Override
    public BlockState getStateForPlacement(BlockPlaceContext ctx) {
        return defaultBlockState().setValue(FACING, ctx.getHorizontalDirection().getOpposite());
    }

    @Override
    protected VoxelShape getShape(BlockState state, BlockGetter level, BlockPos pos, CollisionContext ctx) {
        return shapes[state.getValue(FACING).get2DDataValue()];
    }

    @Override
    protected VoxelShape getCollisionShape(BlockState state, BlockGetter level, BlockPos pos, CollisionContext ctx) {
        return solid ? getShape(state, level, pos, ctx) : Shapes.empty();
    }

    @Override
    protected boolean propagatesSkylightDown(BlockState state, BlockGetter level, BlockPos pos) {
        return true;
    }

    @Override
    protected float getShadeBrightness(BlockState state, BlockGetter level, BlockPos pos) {
        return 1.0f;
    }
}
